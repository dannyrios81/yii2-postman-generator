<?php

namespace iguazoft\postman\tests\fixtures\controllers\v2;

use yii\rest\Controller;

/**
 * @Postman\Collection("Another name that must not wipe previous requests")
 * @Postman\Variable("apiVersion", "v2")
 * @Postman\Variable("storeId", "42")
 */
class ProductController extends Controller
{
    /**
     * @Postman\Request('Delete product')
     * @Postman\Method(DELETE)
     * @Postman\Path("/api/products/:id")
     * @Postman\Auth("apikey", "X-Api-Key", "{{apiKey}}")
     */
    public function actionDelete($id)
    {
    }

    /**
     * @Postman\Request("Internal sync")
     * @Postman\Path("/internal/sync")
     * @Postman\Hidden
     */
    public function actionSync()
    {
    }
}
