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

use App\Models\Transacciones\EvaluacionCalidad;
use App\Models\Transacciones\EvaluacionCalidadCriterio;
use App\Models\Transacciones\EvaluacionCalidadRegistro;
use App\Models\Transacciones\Hallazgo;
/**
* Controlador Evaluación (calidad)
* 
* @package    CIUM API
* @subpackage Controlador
* @author     Eliecer Ramirez Esquinca <ramirez.esquinca@gmail.com>
* @created    2015-07-20

* Controlador `Criterios Recurso`: Maneja los datos para los criterios de las evaluaciones
*
*/
class EvaluacionCalidadController extends Controller 
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
				$evaluacion = EvaluacionCalidad::with("cone","usuarios","cluess")
												->leftJoin('Clues', 'Clues.clues', '=', 'EvaluacionCalidad.clues')
												->leftJoin('Usuario', 'Usuario.id', '=', 'EvaluacionCalidad.idUsuario')
												->whereIn('EvaluacionCalidad.clues',$cluesUsuario)->orderBy($order,$orden);
				
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
				$total=$evaluacion->get();
				$evaluacion = $evaluacion->skip($pagina-1)->take($datos['limite'])->get();
			}
			else
			{
				$evaluacion = EvaluacionCalidad::with("cone","usuarios","cluess")->whereIn('clues',$cluesUsuario)->skip($pagina-1)->take($datos['limite'])->orderBy($order,$orden)->get();
				$total=EvaluacionCalidad::with("cone","usuarios")->whereIn('clues',$cluesUsuario)->get();
			}
			
		}
		else
		{
			$evaluacion = EvaluacionCalidad::with("cone","usuarios")->whereIn('clues',$cluesUsuario)->get();
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
			$usuario = Sentry::getUser();
			// valida si el objeto json evaluaciones exista, esto es para los envios masivos de evaluaciones
			if(array_key_exists("evaluaciones",$datos))
			{
				$datos = (object) $datos;
				foreach($datos->evaluaciones as $item)
				{
					$item = (object) $item;
					if(!array_key_exists("idUsuario",$item))
						$item->idUsuario=$usuario->id;
					$usuario = Sentry::findUserById($item->idUsuario);
					$evaluacion = new EvaluacionCalidad;
					$evaluacion->clues = $item->clues;
					$evaluacion->idUsuario = $item->idUsuario;
					$evaluacion->fechaEvaluacion = $item->fechaEvaluacion;
					$evaluacion->cerrado = $item->cerrado;
					$evaluacion->firma = array_key_exists("firma",$item) ? $item->firma : '';
					$evaluacion->responsable = array_key_exists("responsable",$item) ? $item->responsable : '';
					
					if ($evaluacion->save()) 
					{
						$success = true;
						// si se guarda la evaluacion correctamente.
						// extrae tosdos los registros (columna-expediente) de la evaluación
						foreach($item->registros as $reg)
						{
							$reg = (object) $reg;
							if(!array_key_exists("idUsuario",$reg))
								$reg->idUsuario=$usuario->id;			
							$registro = EvaluacionCalidadRegistro::where('idEvaluacionCalidad',$evaluacion->id)
																 ->where('expediente',$reg->expediente)
																 ->where('idIndicador',$reg->idIndicador)->first();
							if(!$registro)
								$registro = new EvaluacionCalidadRegistro;
							
							$registro->idEvaluacionCalidad = $evaluacion->id;
							$registro->idIndicador = $reg->idIndicador;
							$registro->expediente = $reg->expediente;
							$registro->columna = $reg->columna;
							$registro->cumple = $reg->cumple;
							$registro->promedio = $reg->promedio;
							$registro->totalCriterio = $reg->totalCriterio;
							
							if($registro->save())
							{
								// si se guarda la columna correctamente.
								// extrae tosdos los criterios de la evaluación
								foreach($reg->criterios as $criterio)
								{
									$criterio = (object) $criterio;
									$evaluacionCriterio = EvaluacionCalidadCriterio::where('idEvaluacionCalidad',$evaluacion->id)
																			->where('idCriterio',$criterio->idCriterio)
																			->where('idIndicador',$criterio->idIndicador)
																			->where('idEvaluacionCalidadRegistro',$registro->id)->first();
									
									if(!$evaluacionCriterio)
										$evaluacionCriterio = new EvaluacionCalidadCriterio;
									
									$evaluacionCriterio->idEvaluacionCalidad = $evaluacion->id;
									$evaluacionCriterio->idEvaluacionCalidadRegistro = $registro->id;
									$evaluacionCriterio->idCriterio = $criterio->idCriterio;
									$evaluacionCriterio->idIndicador = $criterio->idIndicador;
									$evaluacionCriterio->aprobado = $criterio->aprobado;
									
									if ($evaluacionCriterio->save()) 
									{								
										$success = true;
									} 
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
							$hallazgo = Hallazgo::where('idIndicador',$hs->idIndicador)->where('idEvaluacion',$evaluacion->id)->first();
			
							if(!$hallazgo)							
								$hallazgo = new Hallazgo;			
													
							$hallazgo->idUsuario = $hs->idUsuario;
							$hallazgo->idAccion = $hs->idAccion;
							$hallazgo->idEvaluacion = $evaluacion->id;
							$hallazgo->idIndicador = $hs->idIndicador;
							$hallazgo->categoriaEvaluacion = 'CALIDAD';
							$hallazgo->idPlazoAccion = $hs->idPlazoAccion;
							$hallazgo->resuelto = $hs->resuelto;
							$hallazgo->descripcion = $hs->descripcion;
							
							if($hallazgo->save())
							{								
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
					$datos->fechaEvaluacion=$date->format('Y-m-d H:i:s');
				$evaluacion = new EvaluacionCalidad;
				$evaluacion->clues = $datos->clues;
				$evaluacion->idUsuario = $datos->idUsuario;
				$evaluacion->fechaEvaluacion = $datos->fechaEvaluacion;
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
		$evaluacion = DB::table('EvaluacionCalidad AS e')
			->leftJoin('Clues AS c', 'c.clues', '=', 'e.clues')
			->leftJoin('ConeClues AS cc', 'cc.clues', '=', 'e.clues')
			->leftJoin('Cone AS co', 'co.id', '=', 'cc.idCone')
            ->leftJoin('Usuario AS us', 'us.id', '=', 'e.idUsuario')
			->leftJoin('ZonaClues AS zc', 'zc.clues', '=', 'e.clues')
			->leftJoin('Zona AS z', 'z.id', '=', 'zc.idZona')
            ->select(array('z.nombre as zona','us.nombres','us.apellidoPaterno','us.apellidoMaterno','e.firma','e.responsable','e.fechaEvaluacion', 'e.cerrado', 'e.id','e.clues', 'c.nombre', 'c.domicilio', 'c.codigoPostal', 'c.entidad', 'c.municipio', 'c.localidad', 'c.jurisdiccion', 'c.institucion', 'c.tipoUnidad', 'c.estatus', 'c.estado', 'c.tipologia','co.nombre as nivelCone', 'cc.idCone'))
            ->where('e.id',"$id");
			
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
		$date=new \DateTime;
		
        DB::beginTransaction();
        try 
		{
			$usuario = Sentry::getUser();
			// valida si el objeto json evaluaciones exista, esto es para los envios masivos de evaluaciones
			if(array_key_exists("evaluaciones",$datos))
			{
				$datos = (object) $datos;
				foreach($datos->evaluaciones as $item)
				{
					$item = (object) $item;
					if(!array_key_exists("idUsuario",$item))
						$item->idUsuario=$usuario->id;
					$usuario = Sentry::findUserById($item->idUsuario);
					$evaluacion = EvaluacionCalidad::find($item->id);;
					$evaluacion->clues = $item->clues;
					$evaluacion->idUsuario = $item->idUsuario;
					$evaluacion->fechaEvaluacion = $item->fechaEvaluacion;
					$evaluacion->cerrado = $item->cerrado;
					$evaluacion->firma = array_key_exists("firma",$item) ? $item->firma : '';
					$evaluacion->responsable = array_key_exists("responsable",$item) ? $item->responsable : '';
					if ($evaluacion->save()) 
					{
						$success = true;
						// si se guarda la evaluacion correctamente.
						// extrae tosdos los registros (columna-expediente) de la evaluación
						foreach($item->registros as $reg)
						{
							$reg = (object) $reg;
							if(!array_key_exists("idUsuario",$reg))
								$reg->idUsuario=$usuario->id;			
							$registro = EvaluacionCalidadRegistro::where('idEvaluacionCalidad',$evaluacion->id)
																 ->where('expediente',$reg->expediente)
																 ->where('idIndicador',$reg->idIndicador)->first();
							if(!$registro)
								$registro = new EvaluacionCalidadRegistro;
							
							$registro->idEvaluacionCalidad = $evaluacion->id;
							$registro->idIndicador = $reg->idIndicador;
							$registro->expediente = $reg->expediente;
							$registro->columna = $reg->columna;
							$registro->cumple = array_key_exists("cumple",$reg) ? $reg->cumple : '';
							$registro->promedio = array_key_exists("promedio",$reg) ? $reg->promedio : '';
							$registro->totalCriterio = $reg->totalCriterio;
							
							if(count($item->hallazgos)==0)
							{
								$hallazgos = Hallazgo::where('idIndicador',$registro->idIndicador)->where('idEvaluacion',$evaluacion->id)->get();
								foreach($hallazgos as $hz)
								{
									$hallazgo = Hallazgo::find($hz->id);
									$hallazgo->delete();
								}
							}
							if(array_key_exists("cumple",$reg)&&array_key_exists("promedio",$reg))
							if($registro->save())
							{
								// si se guarda la columna correctamente.
								// extrae tosdos los criterios de la evaluación
								foreach($reg->criterios as $criterio)
								{
									$criterio = (object) $criterio;
									$evaluacionCriterio = EvaluacionCalidadCriterio::where('idEvaluacionCalidad',$evaluacion->id)
																			->where('idCriterio',$criterio->idCriterio)
																			->where('idIndicador',$criterio->idIndicador)
																			->where('idEvaluacionCalidadRegistro',$registro->id)->first();
									
									if(!$evaluacionCriterio)
										$evaluacionCriterio = new EvaluacionCalidadCriterio;
									
									$evaluacionCriterio->idEvaluacionCalidad = $evaluacion->id;
									$evaluacionCriterio->idEvaluacionCalidadRegistro = $registro->id;
									$evaluacionCriterio->idCriterio = $criterio->idCriterio;
									$evaluacionCriterio->idIndicador = $criterio->idIndicador;
									$evaluacionCriterio->aprobado = $criterio->aprobado;
									
									if ($evaluacionCriterio->save()) 
									{								
										$success = true;
									} 
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
							$hallazgo->categoriaEvaluacion = 'CALIDAD';
							$hallazgo->idPlazoAccion = $hs->idPlazoAccion;
							$hallazgo->resuelto = $hs->resuelto;
							$hallazgo->descripcion = $hs->descripcion;
							
							if($hallazgo->save())
							{								
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
					$datos->fechaEvaluacion=$date->format('Y-m-d H:i:s');
				$evaluacion = EvaluacionCalidad::find($datos->id);;
				$evaluacion->clues = $datos->clues;
				$evaluacion->idUsuario = $datos->idUsuario;
				$evaluacion->fechaEvaluacion = $datos->fechaEvaluacion;
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
			$evaluacion = EvaluacionCalidad::where("id",$id)->where("cerrado","!=",1)->first();
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
	 * Guarde un hallazgo de la evaluación calidad. para generar un hallazgo el promedio de la suma de los criterios debe ser menos al 80% por indicador
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
			
			if(!$hallazgo)
				$hallazgo = new Hallazgo;				
			
			if($datos->get('aprobado')==0)
			{
				if($datos->get('accion'))
				{
					$hallazgo->idUsuario = $usuario->id;
					$hallazgo->idAccion = $datos->get('accion');
					$hallazgo->idEvaluacion = $idEvaluacion;
					$hallazgo->idIndicador = $datos->get('idIndicador');
					$hallazgo->categoriaEvaluacion = 'CALIDAD';
					$hallazgo->idPlazoAccion = array_key_exists('plazoAccion',$datos) ? $datos->get('plazoAccion') : 0;
					$hallazgo->resuelto = $datos->get('resuelto');
					$hallazgo->descripcion = $datos->get('hallazgo');														
					
					$hallazgo->resuelto = 0;				
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
			$clues = Clues::whereIn('clues',$cones)->whereIn('jurisdiccion',$clues)->get();
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