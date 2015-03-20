<?php namespace App\Http\Controllers;
use Request;
use Config;
use Redirect;
use Response;
use Input;
use Cookie;
use Session;
class ClientController extends Controller 
{
	public function getSaludID($state)
	{
		$url=env('URL_SALUDID').'/oauth/oauth?client_id=3&redirect_uri='.env('URL_CIUM').'/login/3&response_type=code&scope=username,email&state='.$state;
		return Redirect::to($url);
	}
}