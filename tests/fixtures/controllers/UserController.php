<?php

namespace iguazoft\postman\tests\fixtures\controllers;

use yii\rest\Controller;

/**
 * This class handles users. The word "class" in this comment must not confuse the scanner.
 *
 * @Postman\Collection("Shop API")
 * @Postman\Description("Public API (v1), used by the mobile app")
 * @Postman\BaseUrl("https://api.shop.test")
 * @Postman\Variable("apiVersion", "v1")
 * @Postman\Test("status", "200")
 * @Postman\Test("header", "Content-Type", "application/json; charset=UTF-8")
 */
class UserController extends Controller
{
    /**
     * @Postman\Request("List users")
     * @Postman\Folder("Users")
     * @Postman\Path("/api/users")
     * @Postman\Description("Returns users (paginated), newest first")
     * @Postman\Auth("bearer", "{{token}}")
     * @Postman\Query("page", "1")
     * @Postman\Query("per-page", "20")
     */
    public function actionIndex()
    {
    }

    /**
     * @Postman\Request("Create user")
     * @Postman\Folder("Users")
     * @Postman\Method("post")
     * @Postman\Path("/api/users")
     * @Postman\Header("X-Request-Id", "{{$guid}}")
 * @Postman\Test("status", "201")
 * @Postman\Test("json", "data.id")
 * @Postman\Test("json", "data.active", "true")
 * @Postman\Test("json", "data.name", "John \"Johnny\" Doe")
 * @Postman\Test("time", "500")
     * @Postman\Body({
     *     "name": "John (Johnny) Doe",
     *     "roles": ["admin", "editor"],
     *     "address": {"city": "Bogotá"}
     * })
     */
    public function actionCreate()
    {
    }

    /**
     * @Postman\Request("View user")
     * @Postman\Folder("Users")
     * @Postman\Path("/api/users/{id}")
     * @Postman\Auth("basic", "{{username}}", "{{password}}")
 * @Postman\Test("contains", "username")
     */
    public function actionView($id)
    {
    }

    /**
     * Not annotated: must be ignored.
     */
    public function actionPlain()
    {
    }
}
