<?php
namespace App\Http\Controllers\v1\Sistema;

use App\Http\Requests;
use App\Http\Controllers\Controller;

use Request;
use Response;
use Input;
use DB; 
use Sentry;
use App\Models\Sistema\SysModulo;
use App\Models\Sistema\SysModuloAccion;
/**
* Controlador Modulo
* 
* @package    CIUM API
* @subpackage Controlador
* @author     Eliecer Ramirez Esquinca <ramirez.esquinca@gmail.com>
* @created    2015-07-20

* Controlador `Modulo`: Manejo los permisos(modulo)
*
*/
class SysModuloController extends Controller {

	/**
	 * Muestra una lista de los recurso según los parametros a procesar en la petición.
	 *
	 * <h3>Lista de parametros Request:</h3>
	 * <Ul>Paginación
	 * <Li> <code>$pagina</code> numero del puntero(offset) para la sentencia limit </ li>
	 * <Li> <code>$limite</code> numero de filas a mostrar por página</ li>	 
	 * </Ul>
	 * <Ul>Busqueda
	 * <Li> <code>$valor</code> string con el valor para hacer la busqueda</ li>
	 * <Li> <code>$order</code> campo de la base de datos por la que se debe ordenar la información. Por Defaul es ASC, pero si se antepone el signo - es de manera DESC</ li>	 
	 * </Ul>
	 *
	 * Ejemplo ordenamiento con respecto a id:
	 * <code>
	 * http://url?pagina=1&limite=5&order=id ASC 
	 * </code>
	 * <code>
	 * http://url?pagina=1&limite=5&order=-id DESC
	 * </code>
	 *
	 * Todo Los parametros son opcionales, pero si existe pagina debe de existir tambien limite
	 * @return Response 
	 * <code style="color:green"> Respuesta Ok json(array("status": 200, "messages": "Operación realizada con exito", "data": array(resultado)),status) </code>
	 * <code> Respuesta Error json(array("status": 404, "messages": "No hay resultados"),status) </code>
	 */
	public function index()
	{
		$datos = Request::all();
		
		// Si existe el paarametro pagina en la url devolver las filas según sea el caso
		// si no existe parametros en la url devolver todos las filas de la tabla correspondiente
		// esta opción es para devolver todos los datos cuando la tabla es de tipo catálogo
		if(array_key_exists('pagina',$datos))
		{
			$pagina=$datos['pagina'];
			if(isset($datos['order']))
			{
				$order = $datos['order'];
				if(strpos(" ".$order,"-"))
					$orden="desc";
				else
					$orden="asc";
				$order=str_replace("-","",$order); 
			}
			else{
				$order="idPadre"; $orden="asc";
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
				$sysModulo = SysModulo::with("Padres")->orderBy($order,$orden);
				
				$search = trim($valor);
				$keyword = $search;
				$sysModulo=$sysModulo->whereNested(function($query) use ($keyword)
				{
					
						$query->Where('nombre', 'LIKE', '%'.$keyword.'%')
							 ->orWhere('controladorLaravel', 'LIKE', '%'.$keyword.'%')
							 ->orWhere('vista', 'LIKE', '%'.$keyword.'%')
							 ->orWhere('idPadre', 'LIKE', '%'.$keyword.'%'); 
				});
				$total=$sysModulo->get();
				$sysModulo = $sysModulo->skip($pagina-1)->take($datos['limite'])->get();
			}
			else
			{
				$sysModulo = SysModulo::with("Padres")->skip($pagina-1)->take($datos['limite'])->orderBy($order,$orden)->orderBy('idPadre', 'ASC')->get();
				$total=SysModulo::with("Padres")->get();
			}
			
		}
		else
		{
			$sysModulo = SysModulo::with("Padres")->orderBy('idPadre', 'ASC')->get();
			$total=$sysModulo;
		}

		if(!$sysModulo)
		{
			return Response::json(array('status'=> 404,"messages"=>'No hay resultados'),404);
		} 
		else 
		{
			return Response::json(array("status"=>200,"messages"=>"Operación realizada con exito","data"=>$sysModulo,"total"=>count($total)),200);
			
		}
	}

	/**
	 * Crear un nuevo registro en la base de datos con los datos enviados
	 *
	 * <h4>Request</h4>
	 * Recibe un input request tipo json de los datos a almacenar en la tabla correspondiente
	 *
	 * @return Response
	 * <code style="color:green"> Respuesta Ok json(array("status": 201, "messages": "Creado", "data": array(resultado)),status) </code>
	 * <code> Respuesta Error json(array("status": 500, "messages": "Error interno del servidor"),status) </code>
	 */
	public function store()
	{
		$rules = [
			'nombre' => 'required|min:3|max:250',
			'metodos'=> 'array'
		];
		$v = \Validator::make(Request::json()->all(), $rules );

		if ($v->fails())
		{
			return Response::json($v->errors());
		}
		$datos = Input::json();
		$success = false;
		
        DB::beginTransaction();
        try 
		{
            $sysModulo = new SysModulo;
            $sysModulo->nombre = $datos->get('nombre');
			$sysModulo->idPadre = $datos->get('idPadre');
			$sysModulo->controladorLaravel = $datos->get('controladorLaravel');
			$sysModulo->vista = $datos->get('vista')?'1':'0';

            if ($sysModulo->save()) 
			{
				// acciones (funciones) a los que se puede acceder en el controller
				foreach($datos->get("metodos") as $item)
				{
					$sysModuloAccion = new SysModuloAccion;
					$sysModuloAccion->nombre = $item['nombre'];				
					$sysModuloAccion->metodo = $item['metodo'];
					$sysModuloAccion->recurso = $item['recurso'];
					$sysModuloAccion->idModulo = $sysModulo->id;
					$sysModuloAccion->save();						
				}
				$success = true;
			}
        } 
		catch (\Exception $e) 
		{
			throw $e;
        }
        if ($success) 
		{
            DB::commit();
			return Response::json(array("status"=>201,"messages"=>"Creado","data"=>$sysModulo),201);
        } 
		else 
		{
            DB::rollback();
			return Response::json(array("status"=>500,"messages"=>"Error interno del servidor"),500);
        }
		
	}

	/**
	 * Devuelve la información del registro especificado.
	 *
	 * @param  int  $id que corresponde al identificador del recurso a mostrar
	 *
	 * @return Response
	 * <code style="color:green"> Respuesta Ok json(array("status": 200, "messages": "Operación realizada con exito", "data": array(resultado)),status) </code>
	 * <code> Respuesta Error json(array("status": 404, "messages": "No hay resultados"),status) </code>
	 */
	public function show($id)
	{
		$sysModulo = SysModulo::with("Padres")->find($id);

		if(!$sysModulo)
		{
			return Response::json(array('status'=> 404,"messages"=>'No hay resultados'),404);
		} 
		else 
		{
			$sysModuloAccion = SysModuloAccion::where("idModulo",$id)->get()->toArray();
			return Response::json(array("status"=>200,"messages"=>"Operación realizada con exito","data"=>$sysModulo, "metodos" => $sysModuloAccion),200);
		}
	}


	/**
	 * Actualizar el  registro especificado en el la base de datos
	 *
	 * <h4>Request</h4>
	 * Recibe un Input Request con el json de los datos
	 *
	 * @param  int  $id que corresponde al identificador del dato a actualizar 	 
	 * @return Response
	 * <code style="color:green"> Respuesta Ok json(array("status": 200, "messages": "Operación realizada con exito", "data": array(resultado)),status) </code>
	 * <code> Respuesta Error json(array("status": 304, "messages": "No modificado"),status) </code>
	 */
	public function update($id)
	{
		$rules = [
			'nombre' => 'required|min:3|max:250',
			'metodos'=> 'array'
		];
		$v = \Validator::make(Request::json()->all(), $rules );

		if ($v->fails())
		{
			return Response::json($v->errors());
		}
		$datos = Input::json();
		$success = false;
        DB::beginTransaction();
        try 
		{
			$sysModulo = SysModulo::find($id);
			$sysModulo->nombre = $datos->get('nombre');
			$sysModulo->idPadre = $datos->get('idPadre');
			$sysModulo->controladorLaravel = $datos->get('controladorLaravel');
			$sysModulo->vista = $datos->get('vista');

            if ($sysModulo->save()) 
			{
				foreach($datos->get("metodos") as $item)
				{					
					$sysModuloAccion = SysModuloAccion::where('idModulo',$id)->where('nombre',$item['nombre'])->where('metodo',$item['metodo'])->first();
				
					if(!$sysModuloAccion)
						$sysModuloAccion = new SysModuloAccion;					
					
					$sysModuloAccion->nombre = $item['nombre'];				
					$sysModuloAccion->metodo = $item['metodo'];
					$sysModuloAccion->recurso = $item['recurso'];
					$sysModuloAccion->idModulo = $id;
					$sysModuloAccion->save();						
				}
				$i=array();
				// Validar las acciones a quitar que no existan en los datos enviados por el usuario
				$sysModuloAccion = SysModuloAccion::where('idModulo',$id)->get();
				if(count($sysModuloAccion)>count($datos->get("metodos")))
				{
					foreach($sysModuloAccion as $ma)
					{
						foreach($datos->get("metodos") as $item)
						{
							if($ma->idModulo == $id && $ma->nombre ==  $item["nombre"] && $ma->metodo == $item['metodo'])
							{
								array_push($i,$ma->id);
							}							
						}
					}
					$sysModuloAccion = SysModuloAccion::where('idModulo',$id)->whereNotIn('id',$i)->delete();
				}
				$success = true;
			}
		} 
		catch (\Exception $e) 
		{
			throw $e;
        }
        if ($success)
		{
			DB::commit();
			return Response::json(array("status"=>200,"messages"=>"Operación realizada con exito","data"=>$sysModulo),200);
		} 
		else 
		{
			DB::rollback();
			return Response::json(array('status'=> 304,"messages"=>'No modificado'),304);
		}
	}

	/**
	 * Elimine el registro especificado del la base de datos (softdelete).
	 *
	 * @param  int  $id que corresponde al identificador del dato a eliminar
	 *
	 * @return Response
	 * <code style="color:green"> Respuesta Ok json(array("status": 200, "messages": "Operación realizada con exito", "data": array(resultado)),status) </code>
	 * <code> Respuesta Error json(array("status": 500, "messages": "Error interno del servidor"),status) </code>
	 */
	public function destroy($id)
	{
		$success = false;
        DB::beginTransaction();
        try 
		{
			$sysModulo = SysModulo::find($id);
			$sysModulo->delete();
			$success=true;
		} 
		catch (\Exception $e) 
		{
			throw $e;
        }
        if ($success)
		{
			DB::commit();
			return Response::json(array("status"=>200,"messages"=>"Operación realizada con exito","data"=>$sysModulo),200);
		} 
		else 
		{
			DB::rollback();
			return Response::json(array('status'=> 500,"messages"=>'Error interno del servidor'),500);
		}
	}
	/**
	 * Muestra una lista de las acciones que corresponde a cada modulo (controller).
	 * 
	 * @return Response
	 * <code style="color:green"> Respuesta Ok json(array("status": 200, "messages": "Operación realizada con exito", "data": array(resultado)),status) </code>
	 * <code> Respuesta Error json(array("status": 404, "messages": "No hay resultados"),status) </code>
	 */
	public function permiso()
	{
		try 
		{			
			$Modulo = SysModulo::orderBy('idPadre', 'ASC')->orderBy('nombre', 'ASC')->get();
			$sysModulo = array();						
					
			foreach($Modulo as $item)
			{	
				$existe=0;
				foreach($item->hijos as $h)
				{
					$accion = []; $hijos = [];
					$acciones = SysModulo::with("Acciones")->find($h->id)->acciones;
					
					foreach($acciones as $ac)
					{
						array_push($accion, $ac->toArray());
						$existe++;						
					}					
					if(count($accion)>0)
						$h["acciones"]=$accion;
					else
						$h["acciones"]=$acciones;
					$item["hijos"]=$h;				
				}
				$acciones = SysModulo::with("Acciones")->find($item->id)->acciones;
				$accion = []; $hijos = []; 
				foreach($acciones as $ac)
				{				
					array_push($accion, $ac->toArray());
					$existe++;
					
				}	
				if($existe)
				{
					$item["acciones"] = $accion;				
					$sysModulo[]=$item;	
				}				
			}		
				
			if(!$sysModulo)
			{
				return Response::json(array('status'=> 404,"messages"=>'No hay resultados'),404);
			} 
			else 
			{
				return Response::json(array("status"=>200,"messages"=>"Operación realizada con exito","data"=>$sysModulo),200);
			}
		} 
		catch (\Exception $e) 
		{
			throw $e;
        }
	}
	/**
	 * Ordena un array.
	 *
	 * Recibe un Input Request con el array a ordenar			
	 * @return Response
	 */
	public function ordenKey()
	{	
		$array=Input::json()->all();
		ksort($array);
		return Response::json($array);
	}
}
