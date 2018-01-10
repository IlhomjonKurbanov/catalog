<?php

namespace modules\catalog\controllers\backend;

use Yii;
use yii\helpers\Html;
use yii\helpers\Url;
use modules\catalog\models\CatalogCategory;
use modules\catalog\models\search\CatalogCategorySearch;
use yii\helpers\VarDumper;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\filters\AccessControl;
use modules\catalog\Module;

/**
 * Class CategoryController
 * @package modules\catalog\controllers\backend
 */
class CategoryController extends Controller
{
    protected $jsFile;

    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::className(),
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'delete' => ['POST'],
                ],
            ],
        ];
    }

    public function init()
    {
        parent::init();

        $this->jsFile = '@modules/catalog/views/ajax/ajax.js';

        // Publish and register the required JS file
        Yii::$app->assetManager->publish($this->jsFile);
        $this->getView()->registerJsFile(
            Yii::$app->assetManager->getPublishedUrl($this->jsFile),
            ['depends' => 'yii\web\JqueryAsset',] // depends
        );
    }

    /**
     * Lists all CatalogCategory models.
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new CatalogCategorySearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);
        $dataProvider->query->orderBy(['lft' => SORT_ASC]);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * @param $id
     */
    public function actionProducts($id)
    {
        $this->redirect(Url::to(['product/index', 'CatalogProductSearch[category_id]' => $id]));
    }

    /**
     * Displays a single CatalogCategory model.
     * @param integer $id
     * @return mixed
     */
    public function actionView($id)
    {
        return $this->render('view', [
            'model' => $this->findModel($id),
        ]);
    }

    /**
     * Creates a new CatalogCategory model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate()
    {
        $model = new CatalogCategory();
        $model->scenario = $model::SCENARIO_CREATE;

        if ($model->load(Yii::$app->request->post())) {
            // Создание корневой категории
            if ($model->parent == 0 && $model->makeRoot()) {
                return $this->redirect(['view', 'id' => $model->id]);
            }

            // Создание подкатегории
            if ($model->parent > 0) {
                $parent = self::findModel($model->parent);
                if ($model->appendTo($parent)) {
                    return $this->redirect(['view', 'id' => $model->id]);
                }
            }
        }
        return $this->render('create', [
            'model' => $model,
        ]);
    }

    /**
     * Updates an existing CatalogCategory model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $id
     * @return mixed
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);
        $model->scenario = $model::SCENARIO_UPDATE;

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id' => $model->id]);
        }
        return $this->render('update', [
            'model' => $model,
        ]);
    }

    /**
     * Clone an existing CatalogCategory model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $id
     * @return mixed
     */
    public function actionClone($id)
    {
        $model = $this->findModel($id);
        $model->scenario = $model::SCENARIO_CREATE;

        $clone = new CatalogCategory();
        $clone->scenario = $model::SCENARIO_CREATE;

        if ($clone->load(Yii::$app->request->post())) {
            // Создание корневой категории
            if ($clone->parent == 0 && $clone->makeRoot()) {
                return $this->redirect(['view', 'id' => $clone->id]);
            }

            // Создание подкатегории
            if ($clone->parent > 0) {
                $parent = self::findModel($clone->parent);
                if ($clone->appendTo($parent)) {
                    return $this->redirect(['view', 'id' => $clone->id]);
                }
            }
        }
        return $this->render('clone', [
            'model' => $model,
        ]);
    }

    /**
     * @param $id
     * @return mixed|\yii\web\Response
     * @throws NotFoundHttpException
     */
    public function actionStatus($id)
    {
        $model = $this->findModel($id);
        $model->scenario = $model::SCENARIO_UPDATE;

        if ($model->isPublish()) {
            $model->status = $model::STATUS_DRAFT;
        } else if ($model->isDraft()) {
            $model->status = $model::STATUS_PUBLISH;
        } else if ($model->isDeleted()) {
            $model->status = $model::STATUS_DRAFT;
        }

        if ($model->save()) {
            if (Yii::$app->request->isAjax) {
                Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
                return [
                    'body' => $model->statusLabelName,
                    'success' => true,
                ];
            }
        }
        return $this->redirect(Yii::$app->request->referrer ?: Url::to(['index']));
    }

    /**
     * Move an existing Category model.
     * If move is successful, the browser will be redirected to the 'index' yii2_static_pages.
     * @param integer $id
     * @return mixed
     */
    public function actionMove($id)
    {
        $model = $this->findModel($id);
        if ($model->depth > 0) {
            $model->scenario = $model::SCENARIO_MOVE;
            $model->parent = $model->parents(1)->one();
            $parent = $model->parent->id;

            if ($model->load(Yii::$app->request->post())) {
                if($model->parent !== $id) {
                    if ($model->parent == 0) {
                        $model->makeRoot();
                    }
                    if ($model->parent > 0 && $model->parent != $parent) {
                        $node = $this->findModel($model->parent);
                        $model->appendTo($node);
                    }
                    if ($model->child > 0 && $model->child != $model->id) {
                        $node = $this->findModel($model->id);
                        $child = $this->findModel($model->child);
                        if ($model->position == $model::POSITION_BEFORE) {
                            $node->insertBefore($child);
                        } else if ($model->position == $model::POSITION_AFTER) {
                            $node->insertAfter($child);
                        } else {
                            $node->insertAfter($child);
                        }
                    }
                    return $this->redirect(['index']);
                }
                Yii::$app->session->setFlash('danger', Module::t('module', 'Error! You can not move a category into itself.'));
            }

            $model->position = $model::POSITION_AFTER;
            return $this->render('move', [
                'model' => $model,
            ]);
        }
        return $this->redirect(['index']);
    }

    /**
     * Deletes an existing CatalogCategory model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param integer $id
     * @return mixed
     */
    public function actionDelete($id)
    {
        $model = $this->findModel($id);
        if ($model->depth > 0) {
            if ($model->isDeleted()) {
                $model->deleteWithChildren();
            } else {
                $model->scenario = $model::SCENARIO_UPDATE;
                $model->status = $model::STATUS_DELETED;
                $model->save();
            }
        }
        return $this->redirect(['index']);
    }

    /**
     * Get Ajax children
     * @param $node_id
     * @param $id
     * @throws NotFoundHttpException
     */
    public function actionLists($node_id, $id = null)
    {
        $model = $this->findModel($node_id);
        $lists = $model->children(1)->all();

        if ($lists) {
            foreach ($lists as $list) {
                if ($list->id == $id) {
                    echo Html::tag('option', '>>> ' . $list->name, ['value' => $list->id]);
                } else {
                    echo Html::tag('option', '> ' . $list->name, ['value' => $list->id]);
                }
            }
        } else {
            echo Html::tag('option', '-', []);
        }
    }

    /**
     * Finds the CatalogCategory model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return CatalogCategory the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = CatalogCategory::findOne($id)) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException(Module::t('module', 'The requested page does not exist.'));
        }
    }
}
