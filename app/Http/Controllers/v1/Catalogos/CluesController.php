<?php
/**
 * Controlador Clues
 * 
 * @package    CIUM API
 * @subpackage Controlador
 * @author     Eliecer Ramirez Esquinca
 * @created    2015-07-20
 */
namespace App\Http\Controllers\v1\Catalogos;

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
	 * Muestra una lista de los recurso.
	 *
	 * @param  
	 *		 get en la url ejemplo url?pagina=1&limite=5&order=id
	 *			pagina = numero del puntero(offset) para la sentencia limit
	 *		    limite = numero de filas a mostrar
	 *			order  = campo de la base de datos por la que se debe ordenar. Defaul ASC si se antepone el signo - es de manera DESC
	 *					 ejemplo url?pagina=1&limite=5&order=id ASC y url?pagina=1&limite=5&order=-id DESC
	 *		    columna= nombre del campo para hacer busqueda
	 *          valor  = valor con el que se buscara en el campo
	 * Los parametros son opcionales, pero si existe pagina debe de existir tambien limite y/o si existe columna debe existir tambien valor y pagina - limite
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
		// Si existe el paarametro pagina en la url devolver las filas según sea el caso
		// si no existe parametros en la url devolver todos las filas de la tabla correspondiente
		// esta opción es para devolver todos los datos cuando la tabla es de tipo catálogo
		if(array_key_exists('pagina',$datos))
		{
			$pagina=$datos['pagina'];
			if(isset($datos['order']))
			{
				if(!$datos['order']=="id")
				$order = $datos['order'];
				else
					$order="clues"; 
				if(strpos(" ".$order,"-"))
					$orden="desc";
				else
					$orden="asc";
				$order=str_replace("-","",$order); 
			}
			else{
				$order="clues"; $orden="asc";
			}
			
			if($pagina == 0)
			{
				$pagina = 1;
			}
			// si existe buscar se realiza esta linea para devolver las filas que en el campo que coincidan con el valor que el usuario escribio
			// si no existe buscar devolver las filas con el limite y la pagina correspondiente a la paginación
			if(array_key_exists('buscar',$datos))
			{
				$columna = $datos['columna'];
				$valor   = $datos['valor'];
				$clues = Clues::whereIn('clues',$cones)->where($columna, 'LIKE', '%'.$valor.'%')->skip($pagina-1)->take($datos['limite'])->orderBy($order,$orden)->get();
				$total=$clues;
			}
			else
			{
				$clues = Clues::whereIn('clues',$cones)->skip($pagina-1)->take($datos['limite'])->orderBy($order,$orden)->get();
				$total=Clues::whereIn('clues',$cones)->get();
			}
			
		}
		else
		{
			$clues = Clues::whereIn('clues',$cones);
			if($jurisdiccion!="")
				$clues=$clues->where("jurisdiccion",$jurisdiccion);
			if(isset($datos["termino"]))
			{
				$value = $datos["termino"];
				$search = trim($value);
				$keywords = preg_split('/[\ \n\,]+/', $search);
				
				$clues=$clues->whereNested(function($query) use ($keywords)
				{
					foreach($keywords as $keyword) {
						$query->Where('jurisdiccion', 'LIKE', '%'.$keyword.'%')
							 ->orWhere('municipio', 'LIKE', '%'.$keyword.'%')
							 ->orWhere('localidad', 'LIKE', '%'.$keyword.'%')
							 ->orWhere('nombre', 'LIKE', '%'.$keyword.'%')
							 ->orWhere('clues', 'LIKE', '%'.$keyword.'%'); 
					}
				});
			}
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
	 * Visualizar el recurso especificado.
	 *
	 * @param  int  $id que corresponde al recurso a mostrar el detalle
	 * Response si el recurso es encontrado devolver el registro y estado 200, si no devolver error con estado 404
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
	 * Muestra una lista de los recurso.
	 *
	 * @param  
	 *		 $clues 
	 * Response devuelve las clues segun el nivel del usuario 1 = Estatal, 2 = jurisdiccional, 3 = zonal, si no encuentra ninguna clues regresa estado 404
	 * @return Response
	 */
	public function CluesUsuario()
	{
		$datos = Request::all();
		// Obtiene el nivel de cone al que pertenece la clues
		$cone=ConeClues::all(["clues"]);
		$cones=array(); $clues=array();
		foreach($cone as $item)
		{
			array_push($cones,$item->clues);
		}	
		$user = Sentry::getUser();	
		
		$cluesUsuario=[];
		// Valida el nivel del usuario 
		if($user->nivel==1)
			$clues = Clues::whereIn('clues',$cones);
		if($user->nivel==2)
		{
			$result = DB::table('UsuarioJurisdiccion')
				->where('idUsuario', $user->id)
				->get();
		
			foreach($result as $item)
			{
				array_push($cluesUsuario,$item->jurisdiccion);
			}
			$clues = Clues::whereIn('clues',$cones)->whereIn('jurisdiccion',$cluesUsuario);
		}
		if($user->nivel==3)
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
			$clues = Clues::whereIn('clues',$cones)->whereIn('clues',$cluesUsuario);
		}
		$value=isset($datos["termino"]) ? $datos["termino"] : '';
		$search = trim($value);
		$keywords = preg_split('/[\ \n\,]+/', $search);
		
		$clues=$clues->whereNested(function($query) use ($keywords)
        {
            foreach($keywords as $keyword) {
                $query->Where('jurisdiccion', 'LIKE', '%'.$keyword.'%')
					 ->orWhere('municipio', 'LIKE', '%'.$keyword.'%')
					 ->orWhere('localidad', 'LIKE', '%'.$keyword.'%')
					 ->orWhere('nombre', 'LIKE', '%'.$keyword.'%')
					 ->orWhere('clues', 'LIKE', '%'.$keyword.'%'); 
            }
        })->get();
			
		if(count($clues)>0)
		{
			return Response::json(array("status"=>200,"messages"=>"ok","data"=>$clues,"total"=>count($clues)),200);			
		} 
		else 
		{
			return Response::json(array("data"=>$clues),200);			
		}
	}
	
	/**
	 * Muestra una lista de las clues que pertenezca a la jurisdicción.
	 *
	 * @param  
	 *		 $jurisdiccion 
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
