<?php
/**
 * Controlador Export
 * 
 * @package    CIUM API
 * @subpackage Controlador
 * @author     Eliecer Ramirez Esquinca <ramirez.esquinca@gmail.com>
 * @created    2015-07-20
 */
namespace App\Http\Controllers\v1;

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
	 * Crear el archivo con información solicitada.
	 *
	 * @param $request 
	 *		 
	 * @return Response
	 */
	public function Export()
	{		
		$datos=Input::json();
		$tabla=$datos->get("tabla");
		$tipo=$datos->get("tipo");
		
		$json_data = array
		(
			"tabla"=>$tabla,
			"tipo"=>$tipo
		);
		
		$url = URL::to("/api/v1/exportGenerate");
		$type = "POST";
		$export = $this->curl($url,$json_data,$type);
		
		$fp = fopen(public_path().'/export.'.$tipo, 'w');
		fwrite($fp, $export);
		fclose($fp);
	}
	/**
	 * Genera el archivo a descargar PDF o EXCEL.
	 *
	 * @param  $request
	 *		 
	 * @return Generar documento
	 */
	public function ExportGenerate()
	{
		$datos=Input::all();
		$tabla=$datos["tabla"];
		$tipo=$datos["tipo"];
		
		$url = URL::to("/api/v1/".$tabla);

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
		
		Excel::create(Session::get('tabla'), function($excel) use($array){
			$excel->sheet(Session::get('tabla'), function($sheet) use($array){													
				$sheet->fromArray( $array );			
			});			
		})->export($tipo);
	}
	
	/**
	 * Hace peticiones a la url solicitada.
	 *
	 * @param  $url = url para hacer la petición
	 *         $json_data = parametros a enviar
	 *         $type = tipo de metodo para la petición (POST, GET, PUT o DELETE)
	 *		 
	 * @return Valor optebido
	 */
	public function curl($url,$json_data=array(),$type)
	{
		$token = str_replace('Bearer ','',Request::header('Authorization'));
		$user = Request::header('X-Usuario');
		
		$headers = array(
	        "Content-type: application/json;charset=\"utf-8\"",
	        "Accept: application/json",
	        "Cache-Control: no-cache",
	        "Pragma: no-cache",
	        "Authorization:Bearer $token",
			"X-Usuario:$user"
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