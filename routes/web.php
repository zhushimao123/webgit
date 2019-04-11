<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});
/*
 * 微信接口
 * 原样返回echostr参数内容，则接入生效
 * */
Route::get('index','WeixinController@index');//第一次get请求
/*
 * 接受微信的推送事件 post   推送事件 关注 取消关注 ...
 */
Route::post('index','WeixinController@wxEven');
/*
 * 获取微信accesstoken
*   access_token是公众号的全局唯一接口调用凭据，
*   默认 get
*/
Route::get('token','WeixinController@token');
/*
 * 获取用户信息
 * userinfo
 */
Route::get('userinfo','WeixinController@userinfo');

