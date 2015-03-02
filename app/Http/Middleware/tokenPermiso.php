<?php namespace App\Http\Middleware;

use Closure;
use Request;
use Response;
use Sentry;
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
        $value  = $action["permisos"];

        $token  = $request->header();
        if(!array_key_exists("authorization",$token))
        	return Response::json(array("msg"=>"No encontrado","status"=>404));
		$token  = $token["authorization"];
        
	    $result = json_decode(file_get_contents('http://SaludID.dev/oauth/check?access_token='.$token[0]));
	    
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
	            return Response::json(array("msg"=>"Prohibido","status"=>403));
	        }

	        $user=Sentry::getUser();
	       	if (!$user->hasAccess($value))
	        	return Response::json(array("msg"=>'No autorizado',"status"=>401));
	        return $next($request); 
	    }
	    else
	    	return Response::json(array("msg"=>'No encontrado',"status"=>404)); 
	}

}
