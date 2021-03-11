<?php

namespace api\http\middleware;

use jwt\JwtLib;
use think\response;

class CheckToken
{
    public function handle($request, \Closure $next)
    {

        $header  = $request->header();

        if( !isset($header['authorization'])){
            $result = [
                'code' => 401,
                'msg'  => 'Missing authorization header',
                'data' => '',
            ];
            return Response::create($result);
        }

        $token = str_replace('Bearer','',$header['authorization']);

        $token = trim($token);

        //验证token
        $jwtRes = JwtLib::verifyJwt($token);

        if($jwtRes['status']===0){

            $result = [
                'code' => 401,
                'msg'  => $jwtRes['msg'],
                'data' => '',
            ];

            return Response::create($result);
        }

        $user_id = $jwtRes ['user_id'];

        $request::instance()->bind('user_id',$user_id);

        return $next($request);
    }
}
