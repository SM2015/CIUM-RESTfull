<?php namespace App\Http\Middleware;

use Closure;
use Response;
class token 
{

	/**
	 * Handle an incoming request.
	 *
	 * @param  \Illuminate\Http\Request  $request
	 * @param  \Closure  $next
	 * @return mixed
	 */
	public function handle($request, Closure $next)
	{
		$token  = $request->header();
        if(!array_key_exists("authorization",$token))
        	return Response::json(array("msg"=>"No encontrado","status"=>404));
		$token  = $token["authorization"];

	    $result = @json_decode(file_get_contents('http://SaludID.dev/oauth/check?access_token='.$token[0]));

	    if (!isset($result->status)) 
	    {
	        return Response::json(array("msg"=>'No encontrado',"status"=>404));   
	    }
	    else
	    	return $next($request);
	}

}
