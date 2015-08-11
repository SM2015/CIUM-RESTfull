<?php namespace App\Http\Controllers\v1\Transacciones;

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
	 * Display a listing of the resource.
	 *
	 * @return Response
	 */
	public function index()
	{
		$datos = Request::all();
		
		$user = Sentry::getUser();	
		$cluesUsuario=$this->permisoZona();
				
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
			if(array_key_exists('buscar',$datos))
			{
				$columna = $datos['columna'];
				$valor   = $datos['valor'];
				$evaluacion = Evaluacion::with("cone","usuarios")->whereIn('clues',$cluesUsuario)->where($columna, 'LIKE', '%'.$valor.'%')->skip($pagina-1)->take($datos['limite'])->orderBy($order,$orden)->orderBy($order,$orden)->get();
				$total=$evaluacion;
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
	 * Store a newly created resource in storage.
	 *
	 * @return Response
	 */
	public function store(EvaluacionRequest $request)
	{
		$datos = Input::json();
		$success = false;
		$date=new \DateTime;
		
        DB::beginTransaction();
        try 
		{
			$usuario = Sentry::getUser();
            $evaluacion = new Evaluacion;
            $evaluacion->clues = $datos->get('clues');
			$evaluacion->idUsuario = $usuario->id;
			$evaluacion->fechaEvaluacion = $date->format('Y-m-d H:i:s');
			if($datos->get("cerrado"))
				$evaluacion->cerrado = $datos->get("cerrado");
			
            if ($evaluacion->save()) 
			{				
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
			return Response::json(array("status"=>201,"messages"=>"Creado","data"=>$evaluacion),201);
        } 
		else 
		{
            DB::rollback();
			return Response::json(array("status"=>500,"messages"=>"Error interno del servidor"),500);
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
	 * Update the specified resource in storage.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function update($id)
	{
		$datos = Input::json();
		$success = false;
        DB::beginTransaction();
        try 
		{
			$usuario = Sentry::getUser();
            $evaluacion = Evaluacion::find($id);
            $evaluacion->clues = $datos->get('clues');
			$evaluacion->idUsuario = $usuario->id;
			if($datos->get("cerrado"))
				$evaluacion->cerrado = $datos->get("cerrado");			

            if ($evaluacion->save()) 
			{				
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
			return Response::json(array("status"=>200,"messages"=>"ok","data"=>$evaluacion),200);
		} 
		else 
		{
			DB::rollback();
			return Response::json(array('status'=> 304,"messages"=>'No modificado'),304);
		}
	}

	/**
	 * Remove the specified resource from storage.
	 *
	 * @param  int  $id
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
	 * Store a newly created resource in storage.
	 *
	 * @return Response
	 */
	public function Criterios()
	{
		$datos = Input::json(); 
		$success = false;
		$date=new \DateTime;
		
        DB::beginTransaction();
        try 
		{
			$usuario = Sentry::getUser();			
			$evaluacionCriterio = EvaluacionCriterio::where('idEvaluacion',$datos->get('idEvaluacion'))->where('idCriterio',$datos->get('idCriterio'))->first();
				
			if(!$evaluacionCriterio)
				$evaluacionCriterio = new EvaluacionCriterio;
			
            $evaluacionCriterio->idEvaluacion = $datos->get('idEvaluacion');
			$evaluacionCriterio->idCriterio = $datos->get('idCriterio');
			$evaluacionCriterio->idIndicador = $datos->get('idIndicador');
			$evaluacionCriterio->aprobado = $datos->get('aprobado');
			
            if ($evaluacionCriterio->save()) 
			{				
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
			return Response::json(array("status"=>201,"messages"=>"Creado","data"=>$evaluacionCriterio),201);
        } 
		else 
		{
            DB::rollback();
			return Response::json(array("status"=>500,"messages"=>"Error interno del servidor"),500);
        }
		
	}
	
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
			/* Obtener el responsable de la unidad medica para los pendientes se cambio al usuario evaluador
			$usuarioPendiente = DB::table('UsuarioClues')					
			->where('clues',$datos->get('clues'))
			->where('esPrincipal',1)->first();
			if(!$usuarioPendiente)
			{
				$usuarioPendiente = $usuario;
				$usuarioPendiente->idUsuario=1;
			}*/
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
					$hallazgo->categoriaEvaluacion = 'ABASTO';
					$hallazgo->idPlazoAccion = array_key_exists('plazoAccion',$datos) ? $datos->get('plazoAccion') : 0;
					$hallazgo->resuelto = $datos->get('resuelto');
					$hallazgo->descripcion = $datos->get('hallazgo');
										
					$accion = Accion::find($datos->get('accion'));
					
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
					
					$hallazgo->save();
					if($accion->tipo == "S")
					{							
						if(!$seguimiento)
							$seguimiento = new Seguimiento;
						
						$seguimiento->idUsuario = $usuario->id;
						$seguimiento->idHallazgo = $hallazgo->id;
						$seguimiento->descripcion = "Inicia seguimiento al hallazgo ".$hallazgo->descripcion." Evaluado por: ".$usuario->nombres." ".$usuario->apellidoPaterno;
						
						$seguimiento->save();
						
						$pendiente = new Pendiente;
						$pendiente->nombre = $usuario->nombres." ".$usuario->apellidoPaterno." (ABASTO) ha creado un hallazgo nuevo #".$hallazgo->id;
						$pendiente->descripcion = "Inicia seguimiento al hallazgo ".$hallazgo->descripcion." Evaluado por: ".$usuario->nombres." ".$usuario->apellidoPaterno;
						$pendiente->idUsuario = $usuarioPendiente;
						$pendiente->recurso = "seguimiento/modificar";
						$pendiente->parametro = "?id=".$hallazgo->id;
						$pendiente->visto = 0;
						$pendiente->save();
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