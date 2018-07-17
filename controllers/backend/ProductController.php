<?php

namespace modules\catalog\controllers\backend;

use Yii;
use yii\helpers\Url;
use modules\catalog\models\CatalogProduct;
use modules\catalog\models\search\CatalogProductSearch;
use moonland\phpexcel\Excel;
use yii\web\UploadedFile;
use modules\catalog\models\Import;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\filters\VerbFilter;
use yii\filters\AccessControl;
use modules\catalog\Module;

/**
 * Class ProductController
 * @package modules\catalog\controllers\backend
 */
class ProductController extends Controller
{
    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'delete' => ['POST'],
                ],
            ],
        ];
    }

    /**
     * Lists all CatalogProduct models.
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new CatalogProductSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Display a detail for GridView
     * @param integer $id
     * @return string
     * @throws NotFoundHttpException
     */
    public function actionDetail($id)
    {
        $model = $this->findModel($id);
        return $this->renderAjax('_detail', [
            'model' => $model,
        ]);
    }

    /**
     * @param integer $id
     * @return string
     * @throws NotFoundHttpException
     */
    public function actionView($id)
    {
        $model = $this->findModel($id);
        return $this->render('view', [
            'model' => $model,
        ]);
    }

    /**
     * Creates a new CatalogProduct model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate()
    {
        $model = new CatalogProduct();

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id' => $model->id]);
        } else {
            return $this->render('create', [
                'model' => $model,
            ]);
        }
    }

    /**
     * @param integer $id
     * @return string|\yii\web\Response
     * @throws NotFoundHttpException
     */
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);
        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id' => $model->id]);
        } else {
            return $this->render('update', [
                'model' => $model,
            ]);
        }
    }

    /**
     * @param integer $id
     */
    public function actionImage($id)
    {
        $this->redirect(Url::to(['product-image/index', 'CatalogProductImageSearch[product_id]' => $id]));
    }

    /**
     * @param integer $id
     * @return array|Response
     * @throws NotFoundHttpException
     */
    public function actionSetStatus($id)
    {
        if (Yii::$app->request->isAjax) {
            Yii::$app->response->format = Response::FORMAT_JSON;
            $result = $this->processChangeStatus($id);
            return [
                'result' => $result->statusLabelName,
            ];
        }
        $this->processChangeStatus($id);
        return $this->redirect(Yii::$app->request->referrer);
    }

    /**
     * @param $id integer
     * @return CatalogProduct
     * @throws NotFoundHttpException
     */
    protected function processChangeStatus($id)
    {
        $model = $this->findModel($id);
        $model->setStatus();
        $model->save(false);
        return $model;
    }

    /**
     * @param integer $id
     * @return \yii\web\Response
     * @throws NotFoundHttpException
     * @throws \Throwable
     * @throws \yii\db\StaleObjectException
     */
    public function actionDelete($id)
    {
        $this->findModel($id)->delete();

        return $this->redirect(['index']);
    }

    /**
     * Finds the CatalogProduct model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return CatalogProduct the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = CatalogProduct::findOne($id)) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException(Module::t('module', 'The requested page does not exist.'));
        }
    }

    /**
     * Export to Excel
     * @throws \yii\base\InvalidConfigException
     */
    public function actionExport()
    {
        $searchModel = new CatalogProductSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams['params']);
        Excel::export([
            'models' => $dataProvider->models,
            'fileName' => $searchModel->getTableSchema()->fullName,
            /*'columns' => [
                [
                    'attribute' => 'code',
                    'format' => 'text',
                    'value' => function ($data) {
                        return $data->code;
                    }
                ],
                [
                    'attribute' => 'name',
                    'format' => 'text',
                    'value' => function ($data) {
                        return $data->name;
                    }
                ],
                [
                    'attribute' => 'description',
                    'format' => 'text',
                    'value' => function ($data) {
                        return $data->description;
                    }
                ],
                [
                    'attribute' => 'availability',
                    'format' => 'decimal',
                    'value' => function ($data) {
                        return $data->availability;
                    }
                ],
                [
                    'attribute' => 'retail',
                    //'format' => 'integer',
                    'value' => function ($data) {
                        return $data->retail;
                    }
                ],
                [
                    'attribute' => 'category_id',
                    'format' => 'text',
                    'value' => function ($data) {
                        return $data->category_id . ' (' . $data->category->stringTreePath . ')';
                    }
                ],
            ],*/

        ]);
    }

    /**
     * @return string|\yii\web\Response
     * @throws \Throwable
     */
    public function actionImport()
    {
        $model = new Import();
        if ($model->load(Yii::$app->request->post())) {
            $file = UploadedFile::getInstance($model, 'file');
            $config = [
                'setFirstRecordAsKeys' => true,
                'setIndexSheetByName' => true,
                //'getOnlySheet' => 'sheet1',
            ];
            $importModel = new CatalogProduct();
            if ($importData = $importModel->processPreparationImportData(Excel::import($file->tempName, $config))) {

                // Если задана опция добавления
                if (isset($model->importOptionsCreate) && $model->importOptionsCreate == true) {
                    $importModel->importItemsCreate($importData);
                }
                // Если задана опция обновления
                if (isset($model->importOptionsUpdate) && $model->importOptionsUpdate == true) {
                    $importModel->importItemsUpdate($importData);
                }
                // Если задана опция удаления
                if (isset($model->importOptionsDelete) && $model->importOptionsDelete == true) {
                    $importModel->importItemsDelete($importData);
                }

                Yii::$app->session->setFlash('success', Module::t('module', 'The import is complete.'));
                return $this->redirect(['index']);
            }
        }
        return $this->render('import', [
            'model' => $model,
        ]);
    }
}
