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
		
		
		$token  = str_replace('Bearer ','',Request::header('Authorization'));	
        if(!$token)
			return Response::json(array("status"=>400,"messages"=>"Petición incorrecta"),400);
       
	    $result = @json_decode(file_get_contents(env('OAUTH_SERVER').'/oauth/check/'.$token));
	   
	    if (isset($result->data) ) 
	    {
			
			if($request->get("Export"))
				return $next($request);
	    	if(!Sentry::check())
			{
				try
				{
					$user = Sentry::findUserByLogin(Request::session()->get('email'));
					Sentry::login($user, false); 
					Request::session()->put('email', Request::session()->get('email'));        
				}
				catch (\Cartalyst\Sentry\Users\UserNotFoundException $e)
				{
					return Response::json(array("status"=>403,"messages"=>"Prohibido"),200);
				}
			}
	        $user=Sentry::getUser();
	       	if (!$user->hasAccess($value))
				return Response::json(array("status"=>401,"messages"=>"No autorizado ".$value),200);
	        return $next($request); 
	    }
	    else
			return Response::json(array("status"=>407,"messages"=>"Autenticación requerida"),407);
	}

}
