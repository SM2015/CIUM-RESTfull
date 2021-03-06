<?php
namespace App\Http\Controllers\v1\Transacciones;

use App\Http\Requests;
use App\Http\Controllers\Controller;

use Response;
use Input;
use DB;
use Sentry;
use Request;

use App\Models\Catalogos\Accion;
use App\Models\Catalogos\Clues;
use App\Models\Catalogos\ConeClues;

use App\Models\Transacciones\Hallazgo;
use App\Models\Transacciones\EvaluacionRecurso;
use App\Models\Transacciones\EvaluacionRecursoCriterio;
/**
* Controlador Evaluación (Recurso)
* 
* @package    CIUM API
* @subpackage Controlador
* @author     Eliecer Ramirez Esquinca <ramirez.esquinca@gmail.com>
* @created    2015-07-20

* Controlador `Calidad`: Proporciona los servicios para el manejos de los datos de la evaluacion
*
*/
class EvaluacionRecursoController extends Controller 
{	
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
		
		$user = Sentry::getUser();	
		$cluesUsuario=$this->permisoZona();
				
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
				$order="fechaEvaluacion"; $orden="desc";
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
				$evaluacion = EvaluacionRecurso::with("cone","usuarios","cluess")
												->leftJoin('Clues', 'Clues.clues', '=', 'EvaluacionRecurso.clues')
												->leftJoin('Usuario', 'Usuario.id', '=', 'EvaluacionRecurso.idUsuario')
												->whereIn('EvaluacionRecurso.clues',$cluesUsuario)->orderBy($order,$orden);
				
				$search = trim($valor);
				$keyword = $search;
				$evaluacion=$evaluacion->whereNested(function($query) use ($keyword)
				{
					
					$query->Where('Clues.clues', 'LIKE', '%'.$keyword.'%')
						 ->orWhere('fechaEvaluacion', 'LIKE', '%'.$keyword.'%')
						 ->orWhere('Clues.jurisdiccion', 'LIKE', '%'.$keyword.'%')
						 ->orWhere('Clues.nombre', 'LIKE', '%'.$keyword.'%')
						 ->orWhere('Usuario.nombres', 'LIKE', '%'.$keyword.'%')
						 ->orWhere('Usuario.apellidoPaterno', 'LIKE', '%'.$keyword.'%')
						 ->orWhere('Usuario.apellidoMaterno', 'LIKE', '%'.$keyword.'%')
						 ->orWhere('cerrado', 'LIKE', '%'.$keyword.'%'); 
				});
				$total = $evaluacion->get();
				$evaluacion = $evaluacion->skip($pagina-1)->take($datos['limite'])->get();
				
				
			}
			else
			{
				$evaluacion = EvaluacionRecurso ::with("cone","usuarios","cluess")->whereIn('EvaluacionRecurso.clues',$cluesUsuario)->skip($pagina-1)->take($datos['limite'])->orderBy($order,$orden)->get();
				$total=EvaluacionRecurso ::whereIn('EvaluacionRecurso.clues',$cluesUsuario)->get();
			}
			
		}
		else
		{
			$evaluacion = EvaluacionRecurso ::with("cone","usuarios","Clues")->whereIn('clues',$cluesUsuario)->get();
			$total=$evaluacion;
		}

		if(!$evaluacion)
		{
			return Response::json(array('status'=> 404,"messages"=>'No hay resultados'),404);
		} 
		else 
		{
			return Response::json(array("status"=>200,"messages"=>"Operación realizada con exito","data"=>$evaluacion,"total"=>count($total)),200);
			
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
		$datos = Request::json()->all();
		if(array_key_exists("evaluaciones",$datos))
		{
			$rules = [
				"evaluaciones" => 'array'
			];
		} 
		else
		{
			$rules = [
				'clues' => 'required|min:3|max:250'
			];
		}
		$v = \Validator::make($datos, $rules );

		if ($v->fails())
		{
			return Response::json($v->errors());
		}		
		$success = false;
		$date=new \DateTime;
		
        DB::beginTransaction();
        try 
		{
			$hayhallazgo = false;
			$usuario = Sentry::getUser();
			// valida si el objeto json evaluaciones exista, esto es para los envios masivos de evaluaciones
			if(array_key_exists("evaluaciones",$datos))
			{	
				$datos = (object) $datos;
				foreach($datos->get('evaluaciones') as $item)
				{
					$item = (object) $item;
					if(!array_key_exists("idUsuario",$item))
						$item->idUsuario=$usuario->id;
					$usuario = Sentry::findUserById($item->idUsuario);
					$evaluacion = new EvaluacionRecurso ;
					$evaluacion->clues = $item->clues;
					$evaluacion->idUsuario = $item->idUsuario;
					$evaluacion->fechaEvaluacion  = $item->fechaEvaluacion ;
					$evaluacion->cerrado = $item->cerrado;
					$evaluacion->firma = array_key_exists("firma",$item) ? $item->firma : '';
					$evaluacion->responsable = array_key_exists("responsable",$item) ? $item->responsable : '';
					
					if ($evaluacion->save()) 
					{
						$success = true;
						// si se guarda la evaluacion correctamente.
						// extrae tosdos los criterios de la evaluación
						foreach($item->criterios as $criterio)
						{
							$criterio = (object) $criterio;
							$evaluacionCriterio = EvaluacionRecursoCriterio::where('idEvaluacionRecurso',$evaluacion->id)
																	->where('idCriterio',$criterio->idCriterio)
																	->where('idIndicador',$criterio->idIndicador)->first();
							
							if(!$evaluacionCriterio)
								$evaluacionCriterio = new EvaluacionRecursoCriterio;
							
							$evaluacionCriterio->idEvaluacionRecurso = $evaluacion->id;
							$evaluacionCriterio->idCriterio = $criterio->idCriterio;
							$evaluacionCriterio->idIndicador = $criterio->idIndicador;
							$evaluacionCriterio->aprobado = $criterio->aprobado;
							
							if ($evaluacionCriterio->save()) 
							{								
								$success = true;
							} 
						}
						// recorrer todos los halazgos encontrados por evaluación
						foreach($item->hallazgos as $hs)
						{
							$hs = (object) $hs;
							if(!array_key_exists("idUsuario",$hs))
								$hs->idUsuario=$usuario->id;
							if(!array_key_exists("idPlazoAccion",$hs))
								$hs->idPlazoAccion=null;
							if(!array_key_exists("resuelto",$hs))
								$hs->resuelto=0;
							$usuario = Sentry::findUserById($hs->idUsuario);
							$usuarioPendiente=$usuario->id;
							
							$hallazgo = Hallazgo::where('idIndicador',$hs->idIndicador)->where('idEvaluacion',$evaluacion->id)->first();
							
							if(!$hallazgo)							
								$hallazgo = new Hallazgo;
													
							$hallazgo->idUsuario = $hs->idUsuario;
							$hallazgo->idAccion = $hs->idAccion;
							$hallazgo->idEvaluacion = $evaluacion->id;
							$hallazgo->idIndicador = $hs->idIndicador;
							$hallazgo->categoriaEvaluacion  = 'RECURSO';
							$hallazgo->idPlazoAccion = $hs->idPlazoAccion;
							$hallazgo->resuelto = $hs->resuelto;
							$hallazgo->descripcion = $hs->descripcion;
							
							if($hallazgo->save())
							{
								$hayhallazgo = true;
								$success=true;
							}								
						}
					} 
				}				
			}
			// si la evaluación es un json de un solo formulario
			else
			{
				$datos = (object) $datos;
				if(!array_key_exists("idUsuario",$datos))
					$datos->idUsuario=$usuario->id;
				if(!array_key_exists("fechaEvaluacion",$datos))
					$datos->fechaEvaluacion =$date->format('Y-m-d H:i:s');
				$evaluacion = new EvaluacionRecurso ;
				$evaluacion->clues = $datos->clues;
				$evaluacion->idUsuario = $datos->idUsuario;
				$evaluacion->fechaEvaluacion  = $datos->fechaEvaluacion ;
				if(array_key_exists("cerrado",$datos))
					$evaluacion->cerrado = $datos->cerrado;
				$evaluacion->firma = array_key_exists("firma",$datos) ? $datos->firma : '';
				$evaluacion->responsable = array_key_exists("responsable",$datos) ? $datos->responsable : '';
				if ($evaluacion->save()) 
				{
					$success = true;
				}
			}                        
        } 
		catch (\Exception $e) 
		{
			throw $e;
        }
        if ($success) 
		{
            DB::commit();
            if($evaluacion->cerrado)
			{
				$spr = DB::select('call sp_recurso()');	
				if($hayhallazgo)
					$sph = DB::select('call sp_hallazgo()');
			}
			return Response::json(array("status"=>201,"messages"=>"Creado","data"=>$evaluacion),201);
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
		$datos = Request::all();
		
		$user = Sentry::getUser();
		$evaluacion = DB::table('EvaluacionRecurso  AS AS')
			->leftJoin('Clues AS c', 'c.clues', '=', 'AS.clues')
			->leftJoin('ConeClues AS cc', 'cc.clues', '=', 'AS.clues')
			->leftJoin('Cone AS co', 'co.id', '=', 'cc.idCone')
			->leftJoin('Usuario AS us', 'us.id', '=', 'AS.idUsuario')
			->leftJoin('ZonaClues AS zc', 'zc.clues', '=', 'AS.clues')
			->leftJoin('Zona AS z', 'z.id', '=', 'zc.idZona')
            ->select(array('z.nombre as zona','us.nombres','us.apellidoPaterno','us.apellidoMaterno','AS.firma','AS.responsable','AS.fechaEvaluacion', 'AS.cerrado', 'AS.id','AS.clues', 'c.nombre', 'c.domicilio', 'c.codigoPostal', 'c.entidad', 'c.municipio', 'c.localidad', 'c.jurisdiccion', 'c.institucion', 'c.tipoUnidad', 'c.estatus', 'c.estado', 'c.tipologia','co.nombre as nivelCone', 'cc.idCone'))
            ->where('AS.id',"$id");
		if(!array_key_exists("dashboard",$datos))
		{
			$cluesUsuario=$this->permisoZona($user->id);
			$evaluacion = $evaluacion->whereIn('c.clues',$cluesUsuario);
		}
		
		$evaluacion = $evaluacion->first();

		if(!$evaluacion)
		{
			return Response::json(array('status'=> 404,"messages"=>'No hay resultados'),404);
		} 
		else 
		{
			return Response::json(array("status"=>200,"messages"=>"Operación realizada con exito","data"=>$evaluacion),200);
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
		$datos = Request::json()->all();
		if(array_key_exists("evaluaciones",$datos))
		{
			$rules = [
				"evaluaciones" => 'array'
			];
		} 
		else
		{
			$rules = [
				'clues' => 'required|min:3|max:250'
			];
		}
		$v = \Validator::make($datos, $rules );

		if ($v->fails())
		{
			return Response::json($v->errors());
		}		
		$success = false;
        DB::beginTransaction();
        try 
		{
			$hayhallazgo = false;
			$usuario = Sentry::getUser();
			// valida si el objeto json evaluaciones exista, esto es para los envios masivos de evaluaciones
			$datos = (object) $datos;
			if(array_key_exists("evaluaciones",$datos))
			{				
				foreach($datos->evaluaciones as $item)
				{
					$item = (object) $item;
					if(!array_key_exists("idUsuario",$item))
						$item->idUsuario=$usuario->id;
					$usuario = Sentry::findUserById($item->idUsuario);
					$evaluacion = EvaluacionRecurso ::find($item->id);
					$evaluacion->clues = $item->clues;
					$evaluacion->idUsuario = $item->idUsuario;
					$evaluacion->fechaEvaluacion  = $item->fechaEvaluacion ;
					$evaluacion->cerrado = $item->cerrado;
					$evaluacion->firma = array_key_exists("firma",$item) ? $item->firma : '';
					$evaluacion->responsable = array_key_exists("responsable",$item) ? $item->responsable : '';
					
					if ($evaluacion->save()) 
					{
						$success=true;
						
						// si se guarda la evaluacion correctamente.
						// extrae tosdos los criterios de la evaluación
						foreach($item->criterios as $criterio)
						{
							$criterio = (object) $criterio;
							
							$borrado = DB::table('EvaluacionRecursoCriterio')								
							->where('idEvaluacionRecurso',$evaluacion->id)
							->where('idCriterio',$criterio->idCriterio)
							->where('idIndicador',$criterio->idIndicador)
							->update(['borradoAL' => NULL]);
					
							$evaluacionCriterio = EvaluacionRecursoCriterio::where('idEvaluacionRecurso',$evaluacion->id)
																	->where('idCriterio',$criterio->idCriterio)
																	->where('idIndicador',$criterio->idIndicador)->first();
							
							if(!$evaluacionCriterio)
								$evaluacionCriterio = new EvaluacionRecursoCriterio;
							
							$evaluacionCriterio->idEvaluacionRecurso = $evaluacion->id;
							$evaluacionCriterio->idCriterio = $criterio->idCriterio;
							$evaluacionCriterio->idIndicador = $criterio->idIndicador;
							$evaluacionCriterio->aprobado = $criterio->aprobado;
							
							if ($evaluacionCriterio->save()) 
							{								
								$success = true;
							}

							if(count($item->hallazgos)==0)
							{
								$hallazgos = Hallazgo::where('idIndicador',$criterio->idIndicador)->where('idEvaluacion',$evaluacion->id)->get();
								foreach($hallazgos as $hz)
								{
									$hallazgo = Hallazgo::find($hz->id);
									$hallazgo->delete();
								}
							}
						}
						
						
						// recorrer todos los halazgos encontrados por evaluación						
						foreach($item->hallazgos as $hs)
						{
							$hs = (object) $hs;
							if(!array_key_exists("idUsuario",$hs))
								$hs->idUsuario=$usuario->id;
							if(!array_key_exists("idPlazoAccion",$hs))
								$hs->idPlazoAccion=null;
							if(!array_key_exists("resuelto",$hs))
								$hs->resuelto=0;
							$usuario = Sentry::findUserById($hs->idUsuario);
							$usuarioPendiente=$usuario->id;
							
							$borrado = DB::table('Hallazgo')					
							->where('idIndicador',$hs->idIndicador)
							->where('idEvaluacion',$evaluacion->id)
							->update(['borradoAL' => NULL]);
							
							$hallazgo = Hallazgo::where('idIndicador',$hs->idIndicador)->where('idEvaluacion',$evaluacion->id)->first();															
							if(!$hallazgo)							
								$hallazgo = new Hallazgo;					
								
							$hallazgo->idUsuario = $hs->idUsuario;
							$hallazgo->idAccion = $hs->idAccion;
							$hallazgo->idEvaluacion = $evaluacion->id;
							$hallazgo->idIndicador = $hs->idIndicador;
							$hallazgo->categoriaEvaluacion  = 'RECURSO';
							$hallazgo->idPlazoAccion = $hs->idPlazoAccion;
							$hallazgo->resuelto = $hs->resuelto;
							$hallazgo->descripcion = $hs->descripcion;
							
							if($hallazgo->save())
							{
								$hayhallazgo = true;
								$success=true;								
							}							
						}
					} 
				}				
			}
			// si la evaluación es un json de un solo formulario
			else
			{
				$datos = (object) $datos;
				if(!array_key_exists("idUsuario",$datos))
					$datos->idUsuario=$usuario->id;
				$evaluacion = EvaluacionRecurso ::find($datos->id);
				$evaluacion->clues = $datos->clues;
				$evaluacion->idUsuario = $datos->idUsuario;
				$evaluacion->fechaEvaluacion  = $datos->fechaEvaluacion ;
				if(array_key_exists("cerrado",$datos))
					$evaluacion->cerrado = $datos->cerrado;
				$evaluacion->firma = array_key_exists("firma",$datos) ? $datos->firma : '';
				$evaluacion->responsable = array_key_exists("responsable",$datos) ? $datos->responsable : '';
				if ($evaluacion->save()) 
				{
					$success = true;
				}
			}                 			
		} 
		catch (\Exception $e) 
		{
			throw $e;
        }
        if ($success)
		{
			DB::commit();
			if($evaluacion->cerrado)
			{
				$spr = DB::select('call sp_recurso()');	
				if($hayhallazgo)
					$sph = DB::select('call sp_hallazgo()');
			}
			return Response::json(array("status"=>200,"messages"=>"Operación realizada con exito","data"=>$evaluacion),200);
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
			$evaluacion = EvaluacionRecurso::where("id",$id)->where("cerrado",null)->orWhere("cerrado",0)->first();
			if($evaluacion)
			{
				$evaluacion->delete();
				$success=true;
			}
			else{
				return Response::json(array('status'=> 304,"messages"=>'No se puede borrar ya fue cerrado'),304);
			}
		} 
		catch (\Exception $e) 
		{
			throw $e;
        }
        if ($success)
		{
			DB::commit();				
			return Response::json(array("status"=>200,"messages"=>"Operación realizada con exito","data"=>$evaluacion),200);
		} 
		else 
		{
			DB::rollback();
			return Response::json(array('status'=> 500,"messages"=>'Error interno del servidor'),500);
		}
	}
	
	
	/**
	 * Guarde un hallazgo de la evaluación recurso. para generar un hallazgo con un criterio que no se cumpla en el indicador
	 *
	 * <h4>Request</h4>
	 * Input request json de los recursos a almacenar en la tabla correspondiente
	 * @return Response
	 * <code style="color:green"> Respuesta Ok json(array("status": 201, "messages": "Creado", "data": array(resultado)),status) </code>
	 * <code> Respuesta Error json(array("status": 500, "messages": "Error interno del servidor"),status) </code>
	 */
	public function Hallazgos()
	{
		$datos = Input::json(); 
		$success = false;
		$date=new \DateTime;
		$idIndicador = $datos->get('idIndicador');
		$idEvaluacion = $datos->get('idEvaluacion');
        DB::beginTransaction();
        try 
		{
			$usuario = Sentry::getUser();
			$borrado = DB::table('Hallazgo')					
			->where('idIndicador',$idIndicador)
			->where('idEvaluacion',$idEvaluacion)
			->update(['borradoAL' => NULL]);
			
			$usuarioPendiente=$usuario->id;
			$hallazgo = Hallazgo::where('idIndicador',$idIndicador)->where('idEvaluacion',$idEvaluacion)->first();
			
			$nuevo=false;
			if(!$hallazgo)
			{
				$nuevo=true;
				$hallazgo = new Hallazgo;
			}					
			
			if($datos->get('aprobado')==0)
			{
				if($datos->get('idAccion'))
				{
					$hallazgo->idUsuario = $usuario->id;
					$hallazgo->idAccion = $datos->get('idAccion');
					$hallazgo->idEvaluacion = $idEvaluacion;
					$hallazgo->idIndicador = $datos->get('idIndicador');
					$hallazgo->categoriaEvaluacion  = 'RECURSO';
					$hallazgo->idPlazoAccion = array_key_exists('idPlazoAccion',$datos) ? $datos->get('idPlazoAccion') : 0;
					$hallazgo->resuelto = $datos->get('resuelto');
					$hallazgo->descripcion = $datos->get('descripcion');
										
					
					if($hallazgo->save())
					{
						$success=true;
					}
				}
			}
			else
			{
				if($hallazgo->id)
				{
					$hallazgo = Hallazgo::find($hallazgo->id);
					$hallazgo->delete();
					$success=true;
				}
			}
		} 
		catch (\Exception $e) 
		{
			throw $e;
        }
        if ($success) 
		{
            DB::commit();
			return Response::json(array("status"=>201,"messages"=>"Creado","data"=>$hallazgo),201);
        } 
		else 
		{
            DB::rollback();
			return Response::json(array("status"=>500,"messages"=>"Error interno del servidor"),500);
        }
	}
	
	/**
	 * Obtener la lista de clues que el usuario tiene acceso.
	 *
	 * get session sentry, usuario logueado
	 * Response si la operacion es exitosa devolver un array con el listado de clues
	 * @return array	 
	 */
	public function permisoZona()
	{
		$cluesUsuario=array();
		$clues=array();
		$cone=ConeClues::all(["clues"]);
		$cones=array();
		foreach($cone as $item)
		{
			array_push($cones,$item->clues);
		}		
		$user = Sentry::getUser();	
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
				array_push($cluesUsuario,$item->clues);
			}
			$clues = Clues::whereIn('clues',$cones)->whereIn('clues',$cluesUsuario)->get();
		}
		$cluesUsuario=array();
		foreach($clues as $item)
		{
			array_push($cluesUsuario,$item->clues);
		}
		return $cluesUsuario;
	}
}
?>