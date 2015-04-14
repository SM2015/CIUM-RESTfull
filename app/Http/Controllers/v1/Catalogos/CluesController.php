<?php namespace App\Http\Controllers\v1\Catalogos;

use App\Http\Requests;
use App\Http\Controllers\Controller;

use Request;
use Response;
use Input;
use DB; 
use Sentry;
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
		$jurisdiccion = $datos['jurisdiccion'];
		$cone=ConeClues::all(["clues"]);
		$cones=array();
		foreach($cone as $item)
		{
			array_push($cones,$item->clues);
		}
		if(array_key_exists('pagina',$datos))
		{
			$pagina=$datos['pagina'];
			if($pagina == 0)
			{
				$pagina = 1;
			}
			if(array_key_exists('buscar',$datos))
			{
				$columna = $datos['columna'];
				$valor   = $datos['valor'];
				$clues = Clues::whereIn('clues',$cones)->where($columna, 'LIKE', '%'.$valor.'%')->skip($pagina-1)->take($datos->get('limite'))->get();
			}
			else
			{
				$clues = Clues::whereIn('clues',$cones)->skip($pagina-1)->take($datos['limite'])->get();
			}
			$total=Clues::whereIn('clues',$cones)->get();
		}
		else
		{
			$clues = Clues::whereIn('clues',$cones)->get();
			$total=$clues;
		}
	
		if(!$clues)
		{
			return Response::json(array('status'=> 404,"messages"=>'No encontrado'),404);
		} 
		else 
		{
			return Response::json(array("status"=>200,"messages"=>"ok","data"=>$clues,"total"=>count($total)),200);
			
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
		if(strpos(" ".$id , "CS"))
		{
			$clues = Clues::with("coneClues")->where('clues','=',$id)->first();
			$cone = ConeClues::with("cone")->where('clues','=',$id)->first();
			$clues["cone"]=$cone;
		}
		else 
		{
			$datos = Request::all();
			$jurisdiccion = $datos['jurisdiccion'];
			$id=$jurisdiccion;
			$clues = Clues::with("coneClues")->where('jurisdiccion','=',$jurisdiccion)->get();
			
			$clues["cone"]="NADA";
		}		
		
		if(!$clues)
		{
			return Response::json(array('status'=> 404,"messages"=>'No encontrado'),404);
		} 
		else 
		{
			return Response::json(array("status"=>200,"messages"=>"ok","data"=>$clues),200);
		}
	}
	
	/**
	 * Display a listing of the resource.
	 *
	 * @return Response
	 */
	public function CluesUsuario()
	{
		$datos = Request::all();
		$cone=ConeClues::all(["clues"]);
		$cones=array();
		foreach($cone as $item)
		{
			array_push($cones,$item->clues);
		}	
		$user = Sentry::getUser();	
		$cluesUsuario=[];
		$result = DB::table('UsuarioClues')
			->select(array('clues'))
			->where('idUsuario', $user->id)
			->get();
		foreach($result as $item)
		{
			array_push($cluesUsuario,$item->clues);
		}
		$clues = Clues::whereIn('clues',$cones)->whereIn('clues',$cluesUsuario)->get();
		$total=$clues;
			
		if(!$clues)
		{
			return Response::json(array('status'=> 404,"messages"=>'No encontrado'),404);
		} 
		else 
		{
			return Response::json(array("status"=>200,"messages"=>"ok","data"=>$clues,"total"=>count($total)),200);
			
		}
	}
	
	/**
	 * Display a listing of the resource.
	 *
	 * @return Response
	 */
	public function jurisdiccion()
	{
		$datos = Request::all();
		$jurisdiccion = $datos['jurisdiccion'];
		$cone=ConeClues::all(["clues"]);
		$cones=array();
		foreach($cone as $item)
		{
			array_push($cones,$item->clues);
		}
		
		$clues = DB::table('Clues')
		->distinct()->select(array('jurisdiccion','entidad'))
		->whereIn('clues',$cones)->get();
		$total=$clues;
		
		if(!$clues)
		{
			return Response::json(array('status'=> 404,"messages"=>'No encontrado'),404);
		} 
		else 
		{
			return Response::json(array("status"=>200,"messages"=>"ok","data"=>$clues,"total"=>count($total)),200);
			
		}
	}
}
