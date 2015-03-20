<?php namespace App\Http\Controllers\Catalogos;

use App\Http\Requests;
use App\Http\Controllers\Controller;

use Request;
use Response;
use Input;
use DB; 
use App\Models\Catalogos\Clues;
use App\Models\Catalogos\ConeClues;


class CluesController extends Controller {

	/**
	 * Display a listing of the resource.
	 *
	 * @return Response
	 */
	public function index()
	{
		$datos = Request::all();
		$valor = $datos['valor'];
		$cone=ConeClues::all(["clues"]);
		$cones=array();
		foreach($cone as $item)
		{
			array_push($cones,$item->clues);
		}
		
		$clues = Clues::whereIn('clues',$cones)->get();
		if(!$clues)
		{
			return Response::json(array('status'=> 404,"messages"=>'No encontrado'),404);
		} 
		else 
		{
			return Response::json(array("status"=>200,"messages"=>"ok","value"=>$clues,"total"=>count($clues)),200);
			
		}
	}

	/**
	 * Display the specified resource.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function show($id)
	{
		$clues = Clues::with("coneClues")->where('clues','=',$id)->first();
		$cone = ConeClues::with("cone")->where('clues','=',$id)->first();
		$clues["cone"]=$cone;
		if(!$clues)
		{
			return Response::json(array('status'=> 404,"messages"=>'No encontrado'),404);
		} 
		else 
		{
			return Response::json(array("status"=>200,"messages"=>"ok","value"=>$clues),200);
		}
	}
}
