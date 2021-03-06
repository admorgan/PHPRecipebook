<?php
App::uses('AppController', 'Controller');
/**
 * Recipes Controller
 *
 * @property Recipe $Recipe
 * @property PaginatorComponent $Paginator
 */
class RecipesController extends AppController {

    /**
     * Components
     *
     * @var array
     */
    public $components = array('Paginator', 'RequestHandler');
    
    public $paginate = array(
        'order' => array(
            'Recipe.name' => 'asc'
        )
    );
    
    // Filter to hide recipes of other users
    public $filterConditions = array();
    
    public function beforeFilter() {
        parent::beforeFilter();
        $this->Auth->allow('findByBase', 'findByCourse', 'findByPrepMethod','search', 'autoCompleteSearch');
        
        //TODO: make this a setting to filter out mine (probably remember last login to get ID)
        //$this->filterConditions = array('Recipe.user_id' => $this->Auth->user('id'));
        $this->filterConditions = array();
    }
    
    
    public function isAuthorized($user) {
        // The owner of a recipe can edit and delete it
        if (in_array($this->action, array('edit', 'delete')) && isset($this->request->params['pass'][0])) {
            $recipeId = (int) $this->request->params['pass'][0];

            if ($this->User->isEditor($user) || $this->Recipe->isOwnedBy($recipeId, $user['id'])) {
                return true;
            }
            else {
                $this->Session->setFlash(__('Not Recipe Owner'));
                return false;
            }
        }

        // Just in case the base controller has something to add
        return parent::isAuthorized($user);
    }

    /**
     * index method
     *
     * @return void
     */
    public function index() {

        if ($this->isMobile) {
            $alphabetList = $this->Recipe->query("SELECT DISTINCT LOWER(SUBSTRING(name, 1, 1)) AS A FROM recipes");
            $this->set('alphabetList', $alphabetList);
            $this->render('alphabet');
        } else {
            $this->Recipe->recursive = 0;
            $this->Paginator->settings = $this->paginate;
            $this->set('recipes', $this->Paginator->paginate('Recipe', $this->filterConditions));
        }
    }

    public function findByBase($baseId) {
        $this->Recipe->recursive = 0;
        $this->filterConditions['Recipe.base_type_id'] = $baseId;
        $this->set('recipes', $this->Paginator->paginate('Recipe', $this->filterConditions));
        $this->render('index');
    }

    public function findByCourse($courseId) {
        $this->Recipe->recursive = 0;
        $this->filterConditions['Recipe.course_id'] = $courseId;
        $this->set('recipes', $this->Paginator->paginate('Recipe', $this->filterConditions));
        $this->render('index');
    }
    
    public function findByPrepMethod($methodId) {
        $this->Recipe->recursive = 0;
        $this->filterConditions['Recipe.preparation_method_id'] = $methodId;
        $this->set('recipes', $this->Paginator->paginate('Recipe', $this->filterConditions));
        $this->render('index');
    }

    /**
     * view method
     *
     * @throws NotFoundException
     * @param string $id
     * @return void
     */
    public function view($id = null, $servings=null) {
        if (!$this->Recipe->exists($id)) {
                throw new NotFoundException(__('Invalid recipe'));
        }
        $this->Recipe->Behaviors->load('Containable');
        $options = array('conditions' => array('Recipe.' . $this->Recipe->primaryKey => $id), 
                'contain' => array(
                    'IngredientMapping' => array(
                        'Ingredient' => array(
                            'fields' => array('name')
                        ),
                        'Unit' => array(
                            'fields' => array('name')
                        )
                    ),
                    'RelatedRecipe' => array(
                        'Related' => array(
                            'fields' => array('id', 'name', 'directions'),
                            'IngredientMapping' => array(
                                'Ingredient' => array(
                                    'fields' => array('name')
                                ),
                                'Unit' => array(
                                    'fields' => array('name')
                                )
                            )
                        )
                    ),
                    'Ethnicity' => array(
                        'fields' => array('name')
                    ),
                    'BaseType' => array(
                        'fields' => array('name')
                    ),
                    'Course' => array(
                        'fields' => array('name')
                    ),
                    'PreparationTime' => array(
                        'fields' => array('name')
                    ),
                    'Difficulty' => array(
                        'fields' => array('name')
                    ),
                    'Source' => array(
                        'fields' => array('name', 'id', 'description')
                    ),
                    'Difficulty' => array(
                        'fields' => array('name')
                    ),
                    'User' => array(
                        'fields' => array('name', 'id')
                    ),
                    'Image',
                    'Review'
                ));
        $this->set('recipe', $this->Recipe->find('first', $options));
        $this->set('servings', $servings);
    }

    /**
     * edit method
     *
     * @throws NotFoundException
     * @param string $id
     * @return void
     */
    public function edit($id = null) {
        if ($id != null && !$this->Recipe->exists($id)) {
           throw new NotFoundException(__('Invalid recipe'));
        }
        if ($this->request->is(array('post', 'put'))) {
            $recipe = $this->request->data;
            //TODO: Keep the original author just in case editor/admin edits
            $recipe['Recipe']['user_id'] = $this->Auth->user('id');
            if ($this->Recipe->saveWithAttachments($recipe)) {
                $this->Session->setFlash(__('The recipe has been saved.'), "success");
            } else {
                $this->Session->setFlash(__('The recipe could not be saved. Please, try again.'));
            }
        } else if ($id != null) {
            //NOTE: This is pretty cool, you can control the depth and properties with 'Containable' and contain.  
            // much better then a loop of crazy custom SQL Code
            $this->Recipe->Behaviors->load('Containable');
            $options = array('conditions' => array('Recipe.' . $this->Recipe->primaryKey => $id), 
                'contain' => array(
                    'IngredientMapping.Ingredient.name', 
                    'RelatedRecipe.Related.name', 
                    'Image'));
            $this->request->data = $this->Recipe->find('first', $options);
            $recipe = $this->request->data;
        }
        
        $ethnicities = $this->Recipe->Ethnicity->find('list');
        $baseTypes = $this->Recipe->BaseType->find('list');
        $courses = $this->Recipe->Course->find('list');
        $preparationTimes = $this->Recipe->PreparationTime->find('list');
        $difficulties = $this->Recipe->Difficulty->find('list');
        $sources = $this->Recipe->Source->find('list');
        $preparationMethods = $this->Recipe->PreparationMethod->find('list');
        $units = $this->Recipe->IngredientMapping->Ingredient->Unit->find('list');
        $this->set(compact('ethnicities', 'baseTypes', 'courses', 'preparationTimes', 'difficulties', 'sources',  'preparationMethods', 'recipe', 'units'));
    }
    
    public function removeIngredientMapping($recipeId, $mappingId) {
        if ($this->Recipe->IngredientMapping->delete($mappingId)) {
            $this->Session->setFlash(__('The ingredient has been removed.'), "success");
        } else {
            $this->Session->setFlash(__('The ingredient could not be removed. Please, try again.'));
        }
    }
    
    public function removeRecipeMapping($recipeId, $mappingId) {
        if ($this->Recipe->RelatedRecipe->delete($mappingId)) {
            $this->Session->setFlash(__('The related recipe has been removed.'), "success");
        } else {
            $this->Session->setFlash(__('The related recipe could not be removed. Please, try again.'));
        }
    }

    /**
     * delete method
     *
     * @throws NotFoundException
     * @param string $id
     * @return void
     */
    public function delete($id = null) {
        $this->Recipe->id = $id;
        if (!$this->Recipe->exists()) {
                throw new NotFoundException(__('Invalid recipe'));
        }
        if ($this->Recipe->delete()) {
                $this->Session->setFlash(__('The recipe has been deleted.'), "success");
        } else {
                $this->Session->setFlash(__('The recipe could not be deleted. Please, try again.'));
        }
        return $this->redirect(array('action' => 'index'));
    }
    
    public function deleteAttachment($recipeId, $id) {
        $this->Recipe->Image->delete($id);
        return $this->redirect(array('action' => 'edit', $recipeId));
    }
    
    public function search() {
        $term = $this->request->query('term');
        if ($term)
        {
            $this->Recipe->recursive = 0;
            $this->Paginator->settings = $this->paginate;
            $this->set('recipes', $this->Paginator->paginate("Recipe", 
                    array_merge($this->filterConditions, array('LOWER(Recipe.name) LIKE' => '%' . trim(strtolower($term)) . '%'))));
        } else {
            $this->set('recipes', $this->Paginator->paginate('Recipe', $this->filterConditions));
        }
        $this->render('index');
    }
    
    public function autoCompleteSearch() {
        $searchResults = array();
        $term = $this->request->query('term');
        if ($term)
        {
            $recipes = $this->Recipe->find('all', array(
                'recursive' => 0,
                'fields' => array('Recipe.id', 'Recipe.name', 'Recipe.serving_size'),
                'conditions' => array_merge($this->filterConditions, array('LOWER(Recipe.name) LIKE ' => '%' . trim(strtolower($term)) . '%'))
            ));

            if (count($recipes) > 0) {
                foreach ($recipes as $item) {
                    $key = $item['Recipe']['name'];
                    $value = $item['Recipe']['id'];
                    $servings = $item['Recipe']['serving_size'];
                    array_push($searchResults, array('id'=>$value, 'value' => strip_tags($key), 'servings' => $servings));
                }
            } else {
                $key = "No Results for '$term' Found";
                array_push($searchResults, array('id'=>'', 'value' => $key, 'servings' => '0'));
            }

            $this->set(compact('searchResults'));
            $this->set('_serialize', 'searchResults');
        }
    }
}
