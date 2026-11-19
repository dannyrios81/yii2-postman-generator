<?php

namespace iguazoft\postman\tests\fixtures\invalid;

use yii\rest\Controller;

class BrokenController extends Controller
{
    /**
     * @Postman\Request("Broken")
     * @Postman\Path("/api/broken")
     * @Postman\Test("statuss", "200")
     */
    public function actionIndex()
    {
    }
}
