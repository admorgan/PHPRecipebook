<?php
App::uses('AppController', 'Controller');
/**
 * ShoppingLists Controller
 *
 * @property ShoppingList $ShoppingList
 * @property PaginatorComponent $Paginator
 */
class ShoppingListsController extends AppController {

    const SHOPPING_LIST = "ShoppingList"; // Session VAR
    public $components = array('Paginator');
    public $helpers = array('Fraction');
    
    // Filter to hide recipes of other users
    public $filterConditions = array();
    
    public function beforeFilter() {
        parent::beforeFilter();
        $this->Auth->deny(); // Deny ALL, user must be logged in.
        
        $this->filterConditions = array('ShoppingList.user_id' => $this->Auth->user('id'));
    }
    
    public function isAuthorized($user) {
        // The owner of a list can edit and delete it. Check every operation
        if (isset($this->request->params['pass'][0])) {
            $listId = (int)$this->request->params['pass'][0];
            if ($this->User->isEditor($user) || $this->ShoppingList->isOwnedBy($listId, $user['id'])) {
                return true;
            } else {
                $this->Session->setFlash(__('Not List Owner'));
                return false;
            }
        }

        // Just in case the base controller has something to add
        return parent::isAuthorized($user);
    }
    
    public function index($id = null) {
        if ($id != null && !$this->ShoppingList->exists($id)) {
                throw new NotFoundException(__('Invalid shopping list'));
        }
        if ($this->request->is(array('post', 'put'))) {
            $this->request->data['ShoppingList']['user_id'] = $this->Auth->user('id');
            if ($this->ShoppingList->saveAll($this->request->data)) {
                $this->Session->setFlash(__('The shopping list has been saved.'), 'success');
                return $this->redirect(array('action' => 'index'));
            } else {
                $this->Session->setFlash(__('The shopping list name could not be saved. Please, try again.'));
            }
        } else {
            $this->request->data = $this->ShoppingList->getList($id, $this->Auth->user('id'));
        }
        $units = $this->ShoppingList->ShoppingListIngredient->Ingredient->Unit->find('list');
        $list = $this->request->data;
        $this->set(compact('list', 'units'));
    }
 
    public function deleteRecipe($listId, $recipeId) {
        $this->ShoppingList->ShoppingListRecipe->recursive = 0;
        $itemId = $this->ShoppingList->ShoppingListRecipe->getIdToDelete($listId, $recipeId, $this->Auth->user('id')); 
        if (isset($itemId) && $itemId > 0) {
            if ($this->ShoppingList->ShoppingListRecipe->delete($itemId)) {
                $this->Session->setFlash(__('The item has been removed.'), 'success');
            } else {
                $this->Session->setFlash(__('The item could not be removed. Please, try again.'));
            }
        } else {
            throw new NotFoundException(__('Invalid recipe item'));
        }
        return $this->redirect(array('action' => 'index'));
    }
    
    public function deleteIngredient($listId, $ingredientId) {
        $this->ShoppingList->ShoppingListIngredient->recursive = 0;
        $itemId = $this->ShoppingList->ShoppingListIngredient->getIdToDelete($listId, $ingredientId, $this->Auth->user('id')); 
        if (isset($itemId) && $itemId > 0) {
            if ($this->ShoppingList->ShoppingListIngredient->delete($itemId)) {
                $this->Session->setFlash(__('The item has been removed.'), 'success');
            } else {
                $this->Session->setFlash(__('The item could not be removed. Please, try again.'));
            }
        } else {
            throw new NotFoundException(__('Invalid ingredient item'));
        }
        return $this->redirect(array('action' => 'index'));
    }
    
    public function addRecipe($id=null) {
        $this->loadModel('Recipe');
        if ($id == null || !$this->Recipe->exists($id)) {
            throw new NotFoundException(__('Invalid recipe'));
        }
        $userId = $this->Auth->user('id');
        $defaultListId = $this->ShoppingList->getDefaultListId($userId);
        
        $newData = array(
            'id' => NULL,
            'shopping_list_id' => $defaultListId,
            'recipe_id' => $id,
            'scale' => 1,
            'user_id' => $userId
        );

        if ($this->ShoppingList->ShoppingListRecipe->save($newData)) {
            $this->Session->setFlash(__('Recipe added to list.'), 'success');
            
        } else {
            $this->Session->setFlash(__('Unable to add recipe to list.'));
        }
        
        return $this->redirect(array('action' => 'index'));
    }
    
    public function addIngredient($id=null) {
        $this->loadModel('Ingredient');
        if ($id == null || !$this->Ingredient->exists($id)) {
            throw new NotFoundException(__('Invalid ingredient'));
        }
        $userId = $this->Auth->user('id');
        $defaultListId = $this->ShoppingList->getDefaultListId($userId);
        
        $newData = array(
            'id' => NULL,
            'shopping_list_id' => $defaultListId,
            'ingredient_id' => $id,
            'quantity' => 1,
            'unit_id' => 1,
            'user_id' => $userId
        );

        if ($this->ShoppingList->ShoppingListIngredient->save($newData)) {
            $this->Session->setFlash(__('Ingredient added to list.'), 'success');
            
        } else {
            $this->Session->setFlash(__('Unable to add ingredient to list.'));
        }
        
        return $this->redirect(array('action' => 'index'));
    }
    
    public function select($listId=null) {
        if ($listId == null) {
            throw new NotFoundException(__('Invalid list'));
        }
        
        $ingredients = $this->loadShoppingList($listId);

        //TODO: Need to: 
        //  * Scale by!
        //  * Related recipes!
        //  * Optionals - option to include optinals (maybe include but show as options). help about what recipe it when with.
        //  * Sort Into Store sections (not important for online)
        $this->set('list', $ingredients); 
        $this->set('listId', $listId);
    }
    
    public function instore($listId=null) {
        if ($listId == null) {
            throw new NotFoundException(__('Invalid list'));
        }
        
        if ($this->request->is(array('post', 'put'))) { 
            $removeIds = isset($this->request->data['remove']) ? $this->request->data['remove'] : NULL;
            $ingredients = $this->removeSelectedItems($listId, $removeIds);
        }
    }
    
    public function online($listId=null) {
        if ($listId == null) {
            throw new NotFoundException(__('Invalid list'));
        }
        
        $this->loadModel('VendorProduct');
        
        if ($this->request->is(array('post', 'put'))) { 
            $removeIds = isset($this->request->data['remove']) ? $this->request->data['remove'] : NULL;
            $ingredients = $this->removeSelectedItems($listId, $removeIds);
        }
        
        $vendors = $this->VendorProduct->Vendor->find('list');
        
        // Load the First Vendor as the Selected one
        if (isset($vendors)) {
            reset($vendors);
            $first_key = key($vendors);
            $selectedVendor = $this->VendorProduct->Vendor->find('first', 
                     array('conditions' => array('Vendor.id' => $first_key)));
            $this->request->data = $selectedVendor;
            $this->set('selectedVendor', $selectedVendor);
        }
              
	$this->set('vendors', $vendors);
    }
    
    private function loadShoppingList($listId) {
        $this->loadModel('Recipe');
        $this->loadModel('Location');

        $ingredients = $this->ShoppingList->getAllIngredients($listId, $this->Auth->user('id'));
        $ingredients = $this->ShoppingList->combineIngredients($ingredients);
        $ingredients = $this->Location->orderShoppingListByLocation($ingredients);
        return $ingredients;
    }
    
    private function removeSelectedItems($listId, $removeIds) {
        $ingredients = $this->loadShoppingList($listId);
        $ingredients = $this->ShoppingList->markIngredientsRemoved($ingredients, $removeIds);
        $this->set('list', $ingredients);
        $this->set('listId', $listId);
        return $ingredients;
    }
 }