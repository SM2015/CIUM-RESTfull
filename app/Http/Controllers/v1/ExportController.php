<?php namespace App\Http\Controllers\v1;

use App\Http\Requests;
use App\Http\Controllers\Controller;

use Request;
use Response;
use Input;
use DB; 
use Excel;
use URL;
use Session;

class ExportController extends Controller {

	/**
	 * Display a listing of the resource.
	 *
	 * @return Response
	 */
	public function Export()
	{		
		
		$tabla=Input::get("tabla");
		$tipo=Input::get("tipo");
		
		Session::put('tabla',$tabla);		
		
		$export = Excel::create(Session::get('tabla'), function($excel) {
			$excel->sheet(Session::get('tabla'), function($sheet) {	
				
				$url = URL::to("/api/v1/".Session::get('tabla'));

				$type='GET';
				
				$json_data = array
				(
					"Export"=>true
				);
		
				$columns = json_decode($this->curl($url,$json_data,$type));
				$columns = (array)($columns->data);
				$array=array();	
				foreach($columns as $item)
					array_push($array,(array)$item);
					
				$sheet->fromArray($array);
				
			});			
		})->export($tipo);
		
		$fp = fopen(URL::to('export.'.$tipo), 'w');
		fwrite($fp, $export);
		fclose($fp);
	}
	/**
	 * Display a listing of the resource.
	 *
	 * @return Response
	 */
	public function ExportOpen()
	{
			
	}
	
	/**
	 * Display a listing of the resource.
	 *
	 * @return Response
	 */
	public function curl($url,$json_data=array(),$type)
	{
		$token = str_replace('Bearer ','',Request::header('Authorization'));
		$headers = array(
	        "Content-type: application/json;charset=\"utf-8\"",
	        "Accept: application/json",
	        "Cache-Control: no-cache",
	        "Pragma: no-cache",
	        "Authorization:Bearer $token"
        );

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL,$url);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $type);
        if(count($json_data)>0)
        {
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($json_data));  
		}

		$datos = curl_exec($ch); 
		
        if (curl_errno($ch)) 
		{
			return curl_errno($ch);
        } 
		else 
		{
        	curl_close($ch);
			return $datos;
		}
	}
}