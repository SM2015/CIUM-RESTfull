<?php
/**
 * Controlador Evaluación (abasto)
 * 
 * @package    CIUM API
 * @subpackage Controlador
 * @author     Eliecer Ramirez Esquinca
 * @created    2015-07-20
 */
namespace App\Http\Controllers\v1\Transacciones;

use App\Http\Requests;
use App\Http\Controllers\Controller;

use Response;
use Input;
use DB;
use Sentry;
use Request;
use App\Models\Transacciones\Evaluacion;
use App\Models\Transacciones\EvaluacionCriterio;
use App\Models\Transacciones\Hallazgo;
use App\Models\Transacciones\Seguimiento;
use App\Models\Catalogos\Accion;
use App\Models\Catalogos\Clues;
use App\Models\Catalogos\ConeClues;
use App\Models\Transacciones\Pendiente;
use App\Models\Transacciones\Notificacion;
use App\Http\Requests\EvaluacionRequest;

class EvaluacionController extends Controller 
{	
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
				$order="id"; $orden="asc";
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
				$evaluacion = Evaluacion::with("cone","usuarios")->whereIn('clues',$cluesUsuario)->orderBy($order,$orden);
				
				$search = trim($valor);
				$keywords = preg_split('/[\ \n\,]+/', $search);
				$evaluacion=$evaluacion->whereNested(function($query) use ($keywords)
				{
					foreach($keywords as $keyword) {
						$query->Where('clues', 'LIKE', '%'.$keyword.'%')
							 ->orWhere('fechaEvaluacion', 'LIKE', '%'.$keyword.'%')
							 ->orWhere('cerrado', 'LIKE', '%'.$keyword.'%'); 
					}
				});
				$total = $evaluacion->get();
				$evaluacion = $evaluacion->skip($pagina-1)->take($datos['limite'])->get();
			}
			else
			{
				$evaluacion = Evaluacion::with("cone","usuarios")->whereIn('clues',$cluesUsuario)->skip($pagina-1)->take($datos['limite'])->orderBy($order,$orden)->orderBy($order,$orden)->get();
				$total=Evaluacion::with("cone","usuarios")->whereIn('clues',$cluesUsuario)->get();
			}
			
		}
		else
		{
			$evaluacion = Evaluacion::with("cone","usuarios")->whereIn('clues',$cluesUsuario)->get();
			$total=$evaluacion;
		}

		if(!$evaluacion)
		{
			return Response::json(array('status'=> 404,"messages"=>'No encontrado'),404);
		} 
		else 
		{
			return Response::json(array("status"=>200,"messages"=>"ok","data"=>$evaluacion,"total"=>count($total)),200);
			
		}
	}

	/**
	 * Guarde un recurso recién creado en el almacenamiento.
	 *
	 * @param post type json de los recursos a almacenar en la tabla correspondiente
	 * Response si la operacion es exitosa devolver el registro y estado 201 si no devolver error y estado 500
	 * @return Response
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
				foreach($datos->get('evaluaciones') as $item)
				{
					$item = (object) $item;
					if(!array_key_exists("idUsuario",$item))
						$item->idUsuario=$usuario->id;
					$usuario = Sentry::findUserById($item->idUsuario);
					$evaluacion = new Evaluacion;
					$evaluacion->clues = $item->clues;
					$evaluacion->idUsuario = $item->idUsuario;
					$evaluacion->fechaEvaluacion = $item->fechaEvaluacion;
					$evaluacion->cerrado = $item->cerrado;
					
					if ($evaluacion->save()) 
					{
						$success = true;
						// si se guarda la evaluacion correctamente.
						// extrae tosdos los criterios de la evaluación
						foreach($item->criterios as $criterio)
						{
							$criterio = (object) $criterio;
							$evaluacionCriterio = EvaluacionCriterio::where('idEvaluacion',$evaluacion->id)
																	->where('idCriterio',$criterio->idCriterio)
																	->where('idIndicador',$criterio->idIndicador)->first();
							
							if(!$evaluacionCriterio)
								$evaluacionCriterio = new EvaluacionCriterio;
							
							$evaluacionCriterio->idEvaluacion = $evaluacion->id;
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
			
							$nuevo=false;
							if(!$hallazgo)
							{
								$nuevo=true;
								$hallazgo = new Hallazgo;
							}					
													
							$hallazgo->idUsuario = $hs->idUsuario;
							$hallazgo->idAccion = $hs->idAccion;
							$hallazgo->idEvaluacion = $evaluacion->id;
							$hallazgo->idIndicador = $hs->idIndicador;
							$hallazgo->categoriaEvaluacion = 'ABASTO';
							$hallazgo->idPlazoAccion = $hs->idPlazoAccion;
							$hallazgo->resuelto = $hs->resuelto;
							$hallazgo->descripcion = $hs->descripcion;
							
							if($hallazgo->save())
							{
								$accion = Accion::find($hs->idAccion);
								
								$borrado = DB::table('Seguimiento')								
								->where('idHallazgo',$hallazgo->id)
								->update(['borradoAL' => NULL]);
								
								$seguimiento = Seguimiento::where("idHallazgo",$hallazgo->id)->first();
								// si el hallazgo tiene seguimiento 
								if($accion->tipo == "S")
								{							
									if(!$seguimiento)
										$seguimiento = new Seguimiento;
									
									$seguimiento->idUsuario = $hs->idUsuario;
									$seguimiento->idHallazgo = $hallazgo->id;
									$seguimiento->descripcion = "Inicia seguimiento al hallazgo ".$hallazgo->descripcion." Evaluado por: ".$usuario->nombres." ".$usuario->apellidoPaterno;
									
									$seguimiento->save();
									if($nuevo)
									{
										$pendiente = new Pendiente;
										$pendiente->nombre = $usuario->nombres." ".$usuario->apellidoPaterno." (ABASTO) ha creado un hallazgo nuevo #".$hallazgo->id;
										$pendiente->descripcion = "Inicia seguimiento al hallazgo ".$hallazgo->descripcion." Evaluado por: ".$usuario->nombres." ".$usuario->apellidoPaterno;
										$pendiente->idUsuario = $usuarioPendiente;
										$pendiente->recurso = "seguimiento/modificar";
										$pendiente->parametro = "?id=".$hallazgo->id;
										$pendiente->visto = 0;
										$pendiente->save();
									}
									$success=true;
								}
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
				$evaluacion = new Evaluacion;
				$evaluacion->clues = $datos->clues;
				$evaluacion->idUsuario = $datos->idUsuario;
				$evaluacion->fechaEvaluacion = $datos->fechaEvaluacion;
				if(array_key_exists("cerrado",$datos))
					$evaluacion->cerrado = $datos->cerrado;
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
	 * Visualizar el recurso especificado.
	 *
	 * @param  int  $id que corresponde al recurso a mostrar el detalle
	 * Response si el recurso es encontrado devolver el registro y estado 200, si no devolver error con estado 404
	 * @return Response
	 */
	public function show($id)
	{
		$datos = Request::all();
		
		$user = Sentry::getUser();
		$evaluacion = DB::table('Evaluacion AS e')
			->leftJoin('Clues AS c', 'c.clues', '=', 'e.clues')
			->leftJoin('ConeClues AS cc', 'cc.clues', '=', 'e.clues')
			->leftJoin('Cone AS co', 'co.id', '=', 'cc.idCone')
            ->select(array('e.fechaEvaluacion', 'e.cerrado', 'e.id','e.clues', 'c.nombre', 'c.domicilio', 'c.codigoPostal', 'c.entidad', 'c.municipio', 'c.localidad', 'c.jurisdiccion', 'c.institucion', 'c.tipoUnidad', 'c.estatus', 'c.estado', 'c.tipologia','co.nombre as nivelCone', 'cc.idCone'))
            ->where('e.id',"$id");
		if(!array_key_exists("dashboard",$datos))
		{
			$cluesUsuario=$this->permisoZona($user->id);
			$evaluacion = $evaluacion->whereIn('c.clues',$cluesUsuario);
		}
		
		$evaluacion = $evaluacion->first();

		if(!$evaluacion)
		{
			return Response::json(array('status'=> 404,"messages"=>'No encontrado'),404);
		} 
		else 
		{
			return Response::json(array("status"=>200,"messages"=>"ok","data"=>$evaluacion),200);
		}
	}


	/**
	 * Actualizar el recurso especificado en el almacenamiento.
	 *
	 * @param  int  $id que corresponde al recurso a actualizar json $request valores a actualizar segun el recurso
	 * Response si el recurso es encontrado y actualizado devolver el registro y estado 200, si no devolver error con estado 304
	 * @return Response
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
					$evaluacion = Evaluacion::find($item->id);
					$evaluacion->clues = $item->clues;
					$evaluacion->idUsuario = $item->idUsuario;
					$evaluacion->fechaEvaluacion = $item->fechaEvaluacion;
					$evaluacion->cerrado = $item->cerrado;
					
					if ($evaluacion->save()) 
					{
						$success=true;
						// si se guarda la evaluacion correctamente.
						// extrae tosdos los criterios de la evaluación
						foreach($item->criterios as $criterio)
						{
							$criterio = (object) $criterio;
							$evaluacionCriterio = EvaluacionCriterio::where('idEvaluacion',$evaluacion->id)
																	->where('idCriterio',$criterio->idCriterio)
																	->where('idIndicador',$criterio->idIndicador)->first();
							
							if(!$evaluacionCriterio)
								$evaluacionCriterio = new EvaluacionCriterio;
							
							$evaluacionCriterio->idEvaluacion = $evaluacion->id;
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
							$nuevo=false;
							if(!$hallazgo)
							{
								$nuevo=true;
								$hallazgo = new Hallazgo;
							}								
													
							$hallazgo->idUsuario = $hs->idUsuario;
							$hallazgo->idAccion = $hs->idAccion;
							$hallazgo->idEvaluacion = $evaluacion->id;
							$hallazgo->idIndicador = $hs->idIndicador;
							$hallazgo->categoriaEvaluacion = 'ABASTO';
							$hallazgo->idPlazoAccion = $hs->idPlazoAccion;
							$hallazgo->resuelto = $hs->resuelto;
							$hallazgo->descripcion = $hs->descripcion;
							
							if($hallazgo->save())
							{
								$accion = Accion::find($hs->idAccion);
								
								$borrado = DB::table('Seguimiento')								
								->where('idHallazgo',$hallazgo->id)
								->update(['borradoAL' => NULL]);
								
								$seguimiento = Seguimiento::where("idHallazgo",$hallazgo->id)->first();
								// si el hallazgo tiene seguimiento 
								if($accion->tipo == "S")
								{							
									if(!$seguimiento)
										$seguimiento = new Seguimiento;
									
									$seguimiento->idUsuario = $hs->idUsuario;
									$seguimiento->idHallazgo = $hallazgo->id;
									$seguimiento->descripcion = "Inicia seguimiento al hallazgo ".$hallazgo->descripcion." Evaluado por: ".$usuario->nombres." ".$usuario->apellidoPaterno;
									
									$seguimiento->save();
									if($nuevo)
									{
										$pendiente = new Pendiente;
										$pendiente->nombre = $usuario->nombres." ".$usuario->apellidoPaterno." (ABASTO) ha creado un hallazgo nuevo #".$hallazgo->id;
										$pendiente->descripcion = "Inicia seguimiento al hallazgo ".$hallazgo->descripcion." Evaluado por: ".$usuario->nombres." ".$usuario->apellidoPaterno;
										$pendiente->idUsuario = $usuarioPendiente;
										$pendiente->recurso = "seguimiento/modificar";
										$pendiente->parametro = "?id=".$hallazgo->id;
										$pendiente->visto = 0;
										$pendiente->save();
									}
									$success=true;
								}
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
				$evaluacion = Evaluacion::find($datos->id);
				$evaluacion->clues = $datos->clues;
				$evaluacion->idUsuario = $datos->idUsuario;
				$evaluacion->fechaEvaluacion = $datos->fechaEvaluacion;
				if(array_key_exists("cerrado",$datos))
					$evaluacion->cerrado = $datos->cerrado;
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
			return Response::json(array("status"=>200,"messages"=>"ok","data"=>$evaluacion),200);
		} 
		else 
		{
			DB::rollback();
			return Response::json(array('status'=> 304,"messages"=>'No modificado'),304);
		}
	}

	/**
	 * Elimine el recurso especificado del almacenamiento (softdelete).
	 *
	 * @param  int  $id que corresponde al recurso a eliminar
	 * Response si el recurso es eliminado devolver el registro y estado 200, si no devolver error con estado 500 
	 * @return Response
	 */
	public function destroy($id)
	{
		$success = false;
        DB::beginTransaction();
        try 
		{
			$evaluacion = Evaluacion::find($id);
			$evaluacion->delete();
			$success=true;
		} 
		catch (\Exception $e) 
		{
			throw $e;
        }
        if ($success)
		{
			DB::commit();
			return Response::json(array("status"=>200,"messages"=>"ok","data"=>$evaluacion),200);
		} 
		else 
		{
			DB::rollback();
			return Response::json(array('status'=> 500,"messages"=>'Error interno del servidor'),500);
		}
	}
	
	
	/**
	 * Guarde un hallazgo en la evaluación.
	 *
	 * @param post type json de los recursos a almacenar en la tabla correspondiente
	 * para generar un hallazgo por lo menos un criterio debe ser no por indicador
	 * Response si la operacion es exitosa devolver el registro y estado 201 si no devolver error y estado 500
	 * @return Response
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
					$hallazgo->categoriaEvaluacion = 'ABASTO';
					$hallazgo->idPlazoAccion = array_key_exists('idPlazoAccion',$datos) ? $datos->get('idPlazoAccion') : 0;
					$hallazgo->resuelto = $datos->get('resuelto');
					$hallazgo->descripcion = $datos->get('descripcion');
										
					$accion = Accion::find($datos->get('idAccion'));
					
					$borrado = DB::table('Seguimiento')								
					->where('idHallazgo',$hallazgo->id)
					->update(['borradoAL' => NULL]);
					
					$hallazgo->resuelto = 0;
					$seguimiento = Seguimiento::where("idHallazgo",$hallazgo->id)->first();
					if($accion->tipo == "R")
					{
						$hallazgo->resuelto = 1;							
						if($seguimiento)
							$seguimiento->delete();
						$success=true;
					}
					// si el hallazgo tiene seguimiento
					$hallazgo->save();
					if($accion->tipo == "S")
					{							
						if(!$seguimiento)
							$seguimiento = new Seguimiento;
						
						$seguimiento->idUsuario = $usuario->id;
						$seguimiento->idHallazgo = $hallazgo->id;
						$seguimiento->descripcion = "Inicia seguimiento al hallazgo ".$hallazgo->descripcion." Evaluado por: ".$usuario->nombres." ".$usuario->apellidoPaterno;
						
						$seguimiento->save();
						
						if($nuevo)
						{
							$pendiente = new Pendiente;
							$pendiente->nombre = $usuario->nombres." ".$usuario->apellidoPaterno." (ABASTO) ha creado un hallazgo nuevo #".$hallazgo->id;
							$pendiente->descripcion = "Inicia seguimiento al hallazgo ".$hallazgo->descripcion." Evaluado por: ".$usuario->nombres." ".$usuario->apellidoPaterno;
							$pendiente->idUsuario = $usuarioPendiente;
							$pendiente->recurso = "seguimiento/modificar";
							$pendiente->parametro = "?id=".$hallazgo->id;
							$pendiente->visto = 0;
							$pendiente->save();
						}
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
	 * @param session sentry, usuario logueado
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