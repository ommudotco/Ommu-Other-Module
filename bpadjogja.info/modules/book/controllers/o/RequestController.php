<?php
/**
 * RequestController
 * @var $this RequestController
 * @var $model BookRequests * @var $form CActiveForm
 * Copyright (c) 2013, Ommu Platform (ommu.co). All rights reserved.
 * version: 0.0.1
 * Reference start
 *
 * TOC :
 *	Index
 *	Manage
 *	Add
 *	Edit
 *	View
 *	RunAction
 *	Delete
 *	Publish
 *
 *	LoadModel
 *	performAjaxValidation
 *
 * @author Putra Sudaryanto <putra.sudaryanto@gmail.com>
 * @copyright Copyright (c) 2014 Ommu Platform (ommu.co)
 * @link http://company.ommu.co
 * @contect (+62)856-299-4114
 *
 *----------------------------------------------------------------------------------------------------------
 */

class RequestController extends Controller
{
	/**
	 * @var string the default layout for the views. Defaults to '//layouts/column2', meaning
	 * using two-column layout. See 'protected/views/layouts/column2.php'.
	 */
	//public $layout='//layouts/column2';
	public $defaultAction = 'index';

	/**
	 * Initialize admin page theme
	 */
	public function init() 
	{
		if(!Yii::app()->user->isGuest) {
			if(in_array(Yii::app()->user->level, array(1,2))) {
				$arrThemes = Utility::getCurrentTemplate('admin');
				Yii::app()->theme = $arrThemes['folder'];
				$this->layout = $arrThemes['layout'];
			} else {
				$this->redirect(Yii::app()->createUrl('site/login'));
			}
		} else {
			$this->redirect(Yii::app()->createUrl('site/login'));
		}
	}

	/**
	 * @return array action filters
	 */
	public function filters() 
	{
		return array(
			'accessControl', // perform access control for CRUD operations
			//'postOnly + delete', // we only allow deletion via POST request
		);
	}

	/**
	 * Specifies the access control rules.
	 * This method is used by the 'accessControl' filter.
	 * @return array access control rules
	 */
	public function accessRules() 
	{
		return array(
			array('allow',  // allow all users to perform 'index' and 'view' actions
				'actions'=>array('index'),
				'users'=>array('*'),
			),
			array('allow', // allow authenticated user to perform 'create' and 'update' actions
				'actions'=>array(),
				'users'=>array('@'),
				'expression'=>'isset(Yii::app()->user->level)',
				//'expression'=>'isset(Yii::app()->user->level) && (Yii::app()->user->level != 1)',
			),
			array('allow', // allow authenticated user to perform 'create' and 'update' actions
				'actions'=>array('manage','add','edit','view','runaction','delete','publish'),
				'users'=>array('@'),
				'expression'=>'isset(Yii::app()->user->level) && in_array(Yii::app()->user->level, array(1,2))',
			),
			array('allow', // allow admin user to perform 'admin' and 'delete' actions
				'actions'=>array(),
				'users'=>array('admin'),
			),
			array('deny',  // deny all users
				'users'=>array('*'),
			),
		);
	}
	
	/**
	 * Lists all models.
	 */
	public function actionIndex() 
	{
		$this->redirect(array('manage'));
	}

	/**
	 * Manages all models.
	 */
	public function actionManage() 
	{
		$model=new BookRequests('search');
		$model->unsetAttributes();  // clear any default values
		if(isset($_GET['BookRequests'])) {
			$model->attributes=$_GET['BookRequests'];
		}

		$columnTemp = array();
		if(isset($_GET['GridColumn'])) {
			foreach($_GET['GridColumn'] as $key => $val) {
				if($_GET['GridColumn'][$key] == 1) {
					$columnTemp[] = $key;
				}
			}
		}
		$columns = $model->getGridColumn($columnTemp);

		$this->pageTitle = 'Book Requests Manage';
		$this->pageDescription = '';
		$this->pageMeta = '';
		$this->render('admin_manage',array(
			'model'=>$model,
			'columns' => $columns,
		));
	}	
	
	/**
	 * Creates a new model.
	 * If creation is successful, the browser will be redirected to the 'view' page.
	 */
	public function actionAdd() 
	{
		$model=new BookRequests;
		$book=new BookMasters;
		$author=new OmmuAuthors;

		// Uncomment the following line if AJAX validation is needed
		$this->performAjaxValidation($model);

		if(isset($_POST['BookRequests'], $_POST['BookMasters'], $_POST['OmmuAuthors'])) {
			$model->attributes=$_POST['BookRequests'];
			$book->attributes=$_POST['BookMasters'];
			$book->scenario='request';
			$author->attributes=$_POST['OmmuAuthors'];
			
			$jsonError = CActiveForm::validate($model);
			$jsonErrorBook = CActiveForm::validate($book);
			$jsonErrorAuthor = CActiveForm::validate($author);
			
			if(strlen($jsonError) > 2 || strlen($jsonErrorBook) > 2 || strlen($jsonErrorAuthor) > 2) {
                $modelArray = json_decode($jsonError, true);
				$bookArray = json_decode($jsonErrorBook, true);
				$authorArray = json_decode($jsonErrorAuthor, true);
                $merge = array_merge_recursive($modelArray, $bookArray);
                $merge = array_merge_recursive($authorArray, $merge);
                $encode = json_encode($merge);
                echo $encode;

			} else {
				if(isset($_GET['enablesave']) && $_GET['enablesave'] == 1) {
					$authorModel = OmmuAuthors::model()->find(array(
						'select' => 'author_id, email',
						'condition' => 'publish = 1 AND email = :email',
						'params' => array(
							':email' => strtolower($author->email),
						),
					));
					if($authorModel != null) {
						$model->requester_id = $authorModel->author_id;
					} else {
						if($author->save())
							$model->requester_id = $author->author_id;
					}
					
					if($model->book_id == '') {
						$bookModel = BookMasters::model()->find(array(
							'select' => 'book_id, title',
							'condition' => 'publish = 1 AND title = :title',
							'params' => array(
								':title' => strtolower($model->book_input),
							),
						));
						if($bookModel != null) {
							$model->book_id = $bookModel->book_id;
							if($model->save()) {
								echo CJSON::encode(array(
									'type' => 5,
									'get' => Yii::app()->controller->createUrl('manage'),
									'id' => 'partial-book-requests',
									'msg' => '<div class="errorSummary success"><strong>BookRequests success created.</strong></div>',
								));
							} else {
								print_r($model->getErrors());
							}
						} else {
							$book->title = $model->book_input;
							if($book->save()) {
								$model->book_id = $book->book_id;
								if($model->save()) {
									echo CJSON::encode(array(
										'type' => 5,
										'get' => Yii::app()->controller->createUrl('manage'),
										'id' => 'partial-book-requests',
										'msg' => '<div class="errorSummary success"><strong>BookRequests success created.</strong></div>',
									));
								} else {
									print_r($model->getErrors());
								}
							} else {
								print_r($book->getErrors());
							}
						}
					} else {
						if($model->save()) {
							echo CJSON::encode(array(
								'type' => 5,
								'get' => Yii::app()->controller->createUrl('manage'),
								'id' => 'partial-book-requests',
								'msg' => '<div class="errorSummary success"><strong>BookRequests success created.</strong></div>',
							));
						} else {
							print_r($model->getErrors());
						}
					}
				}
			}
			Yii::app()->end();
			
		} else {
			$this->pageTitle = 'Create Book Requests';
			$this->pageDescription = '';
			$this->pageMeta = '';
			$this->render('admin_add',array(
				'model'=>$model,
				'book'=>$book,
				'author'=>$author,
			));
		}
	}

	/**
	 * Updates a particular model.
	 * If update is successful, the browser will be redirected to the 'view' page.
	 * @param integer $id the ID of the model to be updated
	 */
	public function actionEdit($id) 
	{
		$model=$this->loadModel($id);

		// Uncomment the following line if AJAX validation is needed
		$this->performAjaxValidation($model);

		if(isset($_POST['BookRequests'])) {
			$model->attributes=$_POST['BookRequests'];
			
			$jsonError = CActiveForm::validate($model);
			if(strlen($jsonError) > 2) {
				echo $jsonError;

			} else {
				if(isset($_GET['enablesave']) && $_GET['enablesave'] == 1) {
					if($model->save()) {
						echo CJSON::encode(array(
							'type' => 5,
							'get' => Yii::app()->controller->createUrl('manage'),
							'id' => 'partial-book-requests',
							'msg' => '<div class="errorSummary success"><strong>BookRequests success updated.</strong></div>',
						));
					} else {
						print_r($model->getErrors());
					}
				}
			}
			Yii::app()->end();
			
		} else {
			$this->pageTitle = 'Update Book Requests';
			$this->pageDescription = '';
			$this->pageMeta = '';
			$this->render('admin_edit',array(
				'model'=>$model,
			));			
		}
	}
	
	/**
	 * Displays a particular model.
	 * @param integer $id the ID of the model to be displayed
	 */
	public function actionView($id) 
	{
		$model=$this->loadModel($id);

		$this->pageTitle = 'View Book Requests';
		$this->pageDescription = '';
		$this->pageMeta = '';
		$this->render('admin_view',array(
			'model'=>$model,
		));
	}	

	/**
	 * Displays a particular model.
	 * @param integer $id the ID of the model to be displayed
	 */
	public function actionRunAction() {
		$id       = $_POST['trash_id'];
		$criteria = null;
		$actions  = $_GET['action'];

		if(count($id) > 0) {
			$criteria = new CDbCriteria;
			$criteria->addInCondition('id', $id);

			if($actions == 'publish') {
				BookRequests::model()->updateAll(array(
					'publish' => 1,
				),$criteria);
			} elseif($actions == 'unpublish') {
				BookRequests::model()->updateAll(array(
					'publish' => 0,
				),$criteria);
			} elseif($actions == 'trash') {
				BookRequests::model()->updateAll(array(
					'publish' => 2,
				),$criteria);
			} elseif($actions == 'delete') {
				BookRequests::model()->deleteAll($criteria);
			}
		}

		// if AJAX request (triggered by deletion via admin grid view), we should not redirect the browser
		if(!isset($_GET['ajax'])) {
			$this->redirect(isset($_POST['returnUrl']) ? $_POST['returnUrl'] : array('manage'));
		}
	}

	/**
	 * Deletes a particular model.
	 * If deletion is successful, the browser will be redirected to the 'admin' page.
	 * @param integer $id the ID of the model to be deleted
	 */
	public function actionDelete($id) 
	{
		$model=$this->loadModel($id);
		
		if(Yii::app()->request->isPostRequest) {
			// we only allow deletion via POST request
			if(isset($id)) {
				if($model->delete()) {
					echo CJSON::encode(array(
						'type' => 5,
						'get' => Yii::app()->controller->createUrl('manage'),
						'id' => 'partial-book-requests',
						'msg' => '<div class="errorSummary success"><strong>BookRequests success deleted.</strong></div>',
					));
				}
			}

		} else {
			$this->dialogDetail = true;
			$this->dialogGroundUrl = Yii::app()->controller->createUrl('manage');
			$this->dialogWidth = 350;

			$this->pageTitle = 'BookRequests Delete.';
			$this->pageDescription = '';
			$this->pageMeta = '';
			$this->render('admin_delete');
		}
	}

	/**
	 * Deletes a particular model.
	 * If deletion is successful, the browser will be redirected to the 'admin' page.
	 * @param integer $id the ID of the model to be deleted
	 */
	public function actionPublish($id) 
	{
		$model=$this->loadModel($id);
		
		if($model->publish == 1) {
			$title = Yii::t('phrase', 'Unpublish');
			$replace = 0;
		} else {
			$title = Yii::t('phrase', 'Publish');
			$replace = 1;
		}

		if(Yii::app()->request->isPostRequest) {
			// we only allow deletion via POST request
			if(isset($id)) {
				//change value active or publish
				$model->publish = $replace;

				if($model->update()) {
					echo CJSON::encode(array(
						'type' => 5,
						'get' => Yii::app()->controller->createUrl('manage'),
						'id' => 'partial-book-requests',
						'msg' => '<div class="errorSummary success"><strong>BookRequests success published.</strong></div>',
					));
				}
			}

		} else {
			$this->dialogDetail = true;
			$this->dialogGroundUrl = Yii::app()->controller->createUrl('manage');
			$this->dialogWidth = 350;

			$this->pageTitle = $title;
			$this->pageDescription = '';
			$this->pageMeta = '';
			$this->render('admin_publish',array(
				'title'=>$title,
				'model'=>$model,
			));
		}
	}

	/**
	 * Returns the data model based on the primary key given in the GET variable.
	 * If the data model is not found, an HTTP exception will be raised.
	 * @param integer the ID of the model to be loaded
	 */
	public function loadModel($id) 
	{
		$model = BookRequests::model()->findByPk($id);
		if($model===null)
			throw new CHttpException(404, Yii::t('phrase', 'The requested page does not exist.'));
		return $model;
	}

	/**
	 * Performs the AJAX validation.
	 * @param CModel the model to be validated
	 */
	protected function performAjaxValidation($model) 
	{
		if(isset($_POST['ajax']) && $_POST['ajax']==='book-requests-form') {
			echo CActiveForm::validate($model);
			Yii::app()->end();
		}
	}
}
