<?php namespace App\Http\Middleware;

use Closure;
use Request;
use Response;
use Sentry;

/**
 * Middleware tokenPermiso
 *
 * @package     APIRESTFULL
 * @subpackage  Middleware
 * @author     	Eliecer
 * @created     2015-16-02
 */
class tokenPermiso {

	/**
	 * Handle an incoming request.
	 *
	 * @param  \Illuminate\Http\Request  $request
	 * @param  \Closure  $next
	 * @return mixed
	 */
	public function handle($request, Closure $next)
	{
		$action = $request->route()->getAction();
		$value = explode('\\',$action["controller"]);
		$value = explode('@',$value[count($value)-1]);
        $value  =$value[0].'.'.$value[1];

        $token  = $request->header();
        if(!array_key_exists("authorization",$token))
			return Response::json(array("status"=>400,"messages"=>"Petición incorrecta"),400);
		$token  = $token["authorization"];
       
	    $result = @json_decode(file_get_contents(env('URL_SALUDID').'/oauth/check?access_token='.$token[0]));
	   
	    if (isset($result->status) && $result->status==1) 
	    {
	    	if(!Sentry::check())
	        try
	        {
	            // Find the user using the user id
	            $user = Sentry::findUserByLogin($result->info->email);
	            // Log the user in
	            Sentry::login($user, false);           
	        }
	        catch (\Cartalyst\Sentry\Users\UserNotFoundException $e)
	        {
				return Response::json(array("status"=>403,"messages"=>"Prohibido"),403);
	        }

	        $user=Sentry::getUser();
	       	if (!$user->hasAccess($value))
				return Response::json(array("status"=>401,"messages"=>"No autorizado"),401);
	        return $next($request); 
	    }
	    else
			return Response::json(array("status"=>407,"messages"=>"Autenticación requerida"),407);
	}

}
