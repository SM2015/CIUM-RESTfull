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
		$jurisdiccion = isset($datos['jurisdiccion']) ? $datos['jurisdiccion'] : '';
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
				$clues = Clues::whereIn('clues',$cones)->where($columna, 'LIKE', '%'.$valor.'%')->skip($pagina-1)->take($datos['limite'])->get();
				$total=$clues;
			}
			else
			{
				$clues = Clues::whereIn('clues',$cones)->skip($pagina-1)->take($datos['limite'])->get();
				$total=Clues::whereIn('clues',$cones)->get();
			}
			
		}
		else
		{
			$clues = Clues::whereIn('clues',$cones);
			if($jurisdiccion!="")
				$clues=$clues->where("jurisdiccion",$jurisdiccion);
			$clues=$clues->get();
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
			$clues = Clues::with("coneClues")->where('jurisdiccion','=',$id)->get();
			
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
		if($user->nivel==1)
			$clues = Clues::whereIn('clues',$cones)->get();
		else if($user->nivel==2)
		{
			$result = DB::table('UsuarioJurisdiccion')
				->where('idUsuario', $user->id)
				->get();
		
			foreach($result as $item)
			{
				array_push($cluesUsuario,$item->jurisdiccion);
			}
			$clues = Clues::whereIn('clues',$cones)->whereIn('jurisdiccion',$cluesUsuario)->get();
		}
		else if($user->nivel==3)
		{
			$result = DB::table('UsuarioZona AS u')
			->leftJoin('Zona AS z', 'z.id', '=', 'u.idZona')
			->leftJoin('ZonaClues AS zu', 'zu.idZona', '=', 'z.id')
			->select(array('zu.clues'))
			->where('u.idUsuario', $user->id)
			->get();
			
			foreach($result as $item)
			{
				array_push($cluesUsuario,$item->jurisdiccion);
			}
			$clues = Clues::whereIn('clues',$cones)->whereIn('jurisdiccion',$cluesUsuario)->get();
		}
		
		
		
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
		$jurisdiccion = isset($datos["jurisdiccion"]) ? $datos["jurisdiccion"]:'';
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
