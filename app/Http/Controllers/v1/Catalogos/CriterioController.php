<?php
namespace App\Http\Controllers\v1\Catalogos;

use App\Http\Requests;
use App\Http\Controllers\Controller;

use Request;
use Response;
use Input;
use DB;
use App\Models\Catalogos\Criterio;
use App\Models\Catalogos\LugarVerificacion;
use App\Models\Catalogos\IndicadorCriterio;
use App\Models\Catalogos\ConeIndicadorCriterio; 
/**
* Controlador Criterio
* 
* @package    CIUM API
* @subpackage Controlador
* @author     Eliecer Ramirez Esquinca <ramirez.esquinca@gmail.com>
* @created    2015-07-20

* Controlador `Criterio`: Manejo del catálogo de los criterios 
*
*/
class CriterioController extends Controller {

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
				$order="Criterio.id"; $orden="asc";
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
				$criterio = Criterio::with("Indicadores")
				->selectRaw("Criterio.id,Criterio.nombre,Criterio.habilitarNoAplica,Criterio.creadoAl,Criterio.modificadoAl,Criterio.borradoAl")
				->leftJoin('IndicadorCriterio', 'IndicadorCriterio.idCriterio', '=', 'Criterio.id')
				->leftJoin('Indicador', 'Indicador.id', '=', 'IndicadorCriterio.idIndicador')
				->orderBy($order,$orden);
				
				$search = trim($valor);
				$keyword = $search;
				$criterio=$criterio->whereNested(function($query) use ($keyword)
				{
					$query->Where('Criterio.nombre', 'LIKE', '%'.$keyword.'%')
						 ->orWhere('Indicador.categoria', 'LIKE', '%'.$keyword.'%')
						 ->orWhere('Indicador.codigo', 'LIKE', '%'.$keyword.'%')
						 ->orWhere('Indicador.nombre', 'LIKE', '%'.$keyword.'%');
				});
				
				$total = $criterio->get();
				$criterio = $criterio->skip($pagina-1)->take($datos['limite'])->get();
			}
			else
			{
				$criterio = Criterio::with("Indicadores")->skip($pagina-1)->take($datos['limite'])->orderBy($order,$orden)->get();
				$total=Criterio::all();
			}
			
		}
		else
		{
			$criterio = Criterio::with("Indicadores")->get();					
			$total=$criterio;
		}

		if(!$criterio)
		{
			return Response::json(array('status'=> 404,"messages"=>'No hay resultados'),404);
		} 
		else 
		{
			foreach($criterio as $cri)
			{
				foreach($cri["indicadores"] as $indicador)
				{
					$indicador->cones=DB::table('ConeIndicadorCriterio AS ci') 
					->leftJoin('Cone AS c', 'c.id', '=', 'ci.idCone')
					->select("*")
					->where('ci.idIndicadorCriterio' , $indicador->pivot->id )
					->where('ci.borradoAl' , null )
					->get();
					
					$pivot = json_encode($indicador->pivot);
					$pivot = (array)json_decode($pivot);
										
					$indicador->lugarVerificacion=lugarVerificacion::find($pivot["idLugarVerificacion"]);																				
				}						
			}
			return Response::json(array("status"=>200,"messages"=>"Operación realizada con exito","data"=>$criterio,"total"=>count($total)),200);
			
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
			'indicadores' => 'array'
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
            $criterio = new Criterio;
            $criterio->nombre = $datos->get('nombre');
            $criterio->habilitarNoAplica = $datos->get('habilitarNoAplica');
			if ($criterio->save()) 
			{
				// optiene la lista de indicadores al que portenece el criterio
				$indicadores = $datos->get('indicadores');
				
				foreach($indicadores as $i)
				{
					// valida que no exista el registro para no dobletear informacion, si existe hace un update si no un insert
					$indicador = IndicadorCriterio::where("idCriterio", $criterio->id)->where("idIndicador", $i["id"])->first();
					if(!$indicador)
						$indicador = new IndicadorCriterio;
					$indicador->idCriterio = $criterio->id;
					$indicador->idIndicador = $i["id"];
					$indicador->idLugarVerificacion = $i["idLugarVerificacion"];
					
					if ($indicador->save()) 
					{
						// obtiene los niveles de cone que estara disponible el criterio
						foreach($i["cones"] as $c)
						{
							// valida que no exista el registro para no dobletear informacion, si existe hace un update si no un insert
							$cone = ConeIndicadorCriterio::where("idIndicadorCriterio", $indicador->id)->where("idCone", $c["id"])->first();
							if(!$cone)
								$cone = new ConeIndicadorCriterio;
							$cone->idIndicadorCriterio = $indicador->id;
							$cone->idCone = $c["id"];
							
							if ($cone->save()) 
							{
								$success = true;								
							}
						}
					}
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
			return Response::json(array("status"=>201,"messages"=>"Creado","data"=>$criterio),201);
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
		$criterio = Criterio::with("Indicadores")->find($id);
		
		if(!$criterio)
		{
			return Response::json(array('status'=> 404,"messages"=>'No hay resultados'),404);
		} 
		else 
		{
			
			foreach($criterio["indicadores"] as $indicador)
			{				
				$indicador->cones=DB::table('ConeIndicadorCriterio AS ci') 
				->leftJoin('Cone AS c', 'c.id', '=', 'ci.idCone')				
				->select("*")
				->where('ci.idIndicadorCriterio' , $indicador->pivot->id )
				->where('ci.borradoAl' , null )
				->get();
				
				$pivot = json_encode($indicador->pivot);
				$pivot = (array)json_decode($pivot);
									
				$indicador->lugarVerificacion=lugarVerificacion::find($pivot["idLugarVerificacion"]);																								
			}
				
			return Response::json(array("status"=>200,"messages"=>"Operación realizada con exito","data"=>$criterio),200);
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
			'indicadores' => 'array'
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
			$criterio = Criterio::find($id);
			$criterio->nombre = $datos->get('nombre');
			$criterio->habilitarNoAplica = $datos->get('habilitarNoAplica');
			
            if ($criterio->save()) 
			{
				$indicadores = $datos->get('indicadores');
				$indicador = IndicadorCriterio::where("idCriterio", $criterio->id)->get();	
				foreach($indicador as $i)
				{
					$ic=IndicadorCriterio::find($i->id);
					$ic->delete();
					$cone = ConeIndicadorCriterio::where("idIndicadorCriterio", $ic->id)->get();
					foreach($cone as $c)
					{
						$cic=ConeIndicadorCriterio::find($c->id);
						$cic->delete();
					}
				}
				
				foreach($indicadores as $i)
				{
					$borrado = DB::table('IndicadorCriterio')							
					->where('idCriterio',$criterio->id)
					->where('idIndicador',$i["id"])
					->update(['borradoAl' => NULL]);
					$indicador = IndicadorCriterio::where("idCriterio", $criterio->id)->where("idIndicador", $i["id"])->first();
					if(!$indicador)
						$indicador = new IndicadorCriterio;
					$indicador->idCriterio = $criterio->id;
					$indicador->idIndicador = $i["id"];
					$indicador->idLugarVerificacion = $i["idLugarVerificacion"];
					
					if ($indicador->save()) 
					{						
						foreach($i["cones"] as $c)
						{
							$borrado = DB::table('ConeIndicadorCriterio')							
							->where('idIndicadorCriterio',$indicador->id)
							->where('idCone',$c["id"])
							->update(['borradoAl' => NULL]);
							
							$cone = ConeIndicadorCriterio::where("idIndicadorCriterio", $indicador->id)->where("idCone", $c["id"])->first();
							if(!$cone)
								$cone = new ConeIndicadorCriterio;
							$cone->idIndicadorCriterio = $indicador->id;
							$cone->idCone = $c["id"];
							
							if ($cone->save()) 
							{
								$success = true;								
							}
						}
					}
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
			return Response::json(array("status"=>200,"messages"=>"Operación realizada con exito","data"=>$criterio),200);
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
			$criterio = Criterio::find($id);
			$criterio->delete();
			$success=true;
		} 
		catch (\Exception $e) 
		{
			throw $e;
        }
        if ($success)
		{
			DB::commit();
			return Response::json(array("status"=>200,"messages"=>"Operación realizada con exito","data"=>$criterio),200);
		} 
		else 
		{
			DB::rollback();
			return Response::json(array('status'=> 500,"messages"=>'Error interno del servidor'),500);
		}
	}
}
