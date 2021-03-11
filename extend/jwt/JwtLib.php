<?php
/**
 * Desc: 用于App 生成token  跨域认证（token 须与前台用户关联）
 * User: Zhaojinsheng
 * Date: 2020/8/4
 * Time: 11:08
 * Filename:JwtLib.php
 */
namespace jwt;

use Firebase\JWT\JWT;
use think\Loader;

class JwtLib {


    /**
     * Desc: 生成token
     * User: Zhaojinsheng
     * Date: 2020/8/4
     * Time: 11:53
     * @param $userId
     * @return string
     */
    public static function createJwt($userId)
    {
        $key = config('jwt.key'); //jwt的签发密钥，验证token的时候需要用到

        $token = array(
            "user_id" => $userId,
            "iss" => config('jwt.iss'),//签发组织
            "aud" => config('jwt.aud'), //签发作者
            "lat" => config('jwt.lat'),
            "nbf" => config('jwt.nbf'),
            "exp" => config('jwt.exp')
        );


        $jwt =JWT::encode($token, $key);

        return $jwt;
    }
    /**
     * Desc: 校验token方法
     * User: Zhaojinsheng
     * Date: 2020/8/4
     * Time: 11:50
     * @param $jwt
     * @return array|\Exception|\Firebase\JWT\ExpiredException|\Firebase\JWT\SignatureInvalidException|Exception
     */
    public  static function verifyJwt($jwt)
    {
        $key = config('jwt.key'); //jwt的签发密钥，验证token的时候需要用到

        try {
            $jwtAuth = json_encode(JWT::decode($jwt, $key, array('HS256')));
            $authInfo = json_decode($jwtAuth, true);
            $msg = [];
            if (!empty($authInfo['user_id'])) {
                $msg = [
                    'status' => 1,
                    'msg' => 'Token验证通过',
                    'user_id' => $authInfo['user_id']
                ];
            } else {
                $msg = [
                    'status' => 0,
                    'msg' => 'Token验证不通过,用户ID不存在'
                ];
            }

        } catch (\Firebase\JWT\SignatureInvalidException $e) {


            $msg = [
                'status' => 0,
                'msg' => 'Token无效'
            ];


        } catch (\Firebase\JWT\ExpiredException $e) {

            $msg =  [
                'status' => 0,
                'msg' => 'Token过期'
            ];

        } catch (\Exception $e) {
            $msg = [
                'status' => 0,
                'msg' => $e->getMessage(),
            ];
        }

        return  $msg;
    }




}