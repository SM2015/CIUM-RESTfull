<?php namespace App\Http\Controllers\Transacciones;

use App\Http\Requests;
use App\Http\Controllers\Controller;

use Response;
use Input;
use DB;
use Sentry;
use App\Models\Transacciones\Evaluacion;
use App\Models\Transacciones\EvaluacionCriterio;
use App\Models\Transacciones\Hallazgo;
use App\Models\Transacciones\Seguimiento;
use App\Http\Requests\EvaluacionRequest;

class EvaluacionController extends Controller 
{
	public function  __construct()
	{
		$this->middleware('tokenPermiso',['only'=> ['index'],'permisos'=>'Evaluacion:index']);
	}
	
    /**
	 * Display a listing of the resource.
	 *
	 * @return Response
	 */
	public function index()
	{
		$datos = Request::all();
		
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
				$evaluacion = Evaluacion::with("Padres")->where($columna, 'LIKE', '%'.$valor.'%')->skip($pagina-1)->take($datos->get('limite'))->get();
			}
			else
			{
				$evaluacion = Evaluacion::with("Padres")->skip($pagina-1)->take($datos['limite'])->get();
			}
			$total=Evaluacion::with("Padres")->get();
		}
		else
		{
			$evaluacion = Evaluacion::with("Padres")->get();
			$total=$evaluacion;
		}

		if(!$evaluacion)
		{
			return Response::json(array('status'=> 404,"messages"=>'No encontrado'),404);
		} 
		else 
		{
			return Response::json(array("status"=>200,"messages"=>"ok","value"=>$evaluacion,"total"=>count($total)),200);
			
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
			$usuario = Sentry::getUser();var_dump($usuario);die();
            $evaluacion = new Evaluacion;
            $evaluacion->clues = $datos->get('idClues');
			$evaluacion->idUsuario = $usuario;
			$evaluacion->fechaEvaluacion = $date->format('Y-m-d H:i:s');
			if($datos->get("cerrado"))
				$evaluacion->fechaEvaluacion = $datos->get("cerrado");
			if($datos->get('idClues'))

            if ($evaluacion->save()) 
			{
				$id=$evaluacion->id;
				foreach ($datos->get("criterio") as $clave => $valor) 
				{
					$aprobado = $datos->get("aprobado");
					$hallazgos = $datos->get("hallazgo");
					$acciones = $datos->get("accion");
					$plazoAccion = $datos->get("plazoAccion");
					
					$evaluacionCriterio = new EvaluacionCriterio;
					$evaluacionCriterio->idEvaluacion = $id;
					$evaluacionCriterio->idCriterio = $key;
						
					if($clave==$aprobado[$clave])
					{						
						$evaluacionCriterio->aprobado = 1;
						$evaluacionCriterio->save();
					}
					else
					{
						$evaluacionCriterio->aprobado = 0;
						if($evaluacionCriterio->save())
						{
							$hallazgo = new Hallazgo;
							$hallazgo->idUsuario = $usuario;
							$hallazgo->idAccion = $acciones[$key];
							$hallazgo->idEvaluacionCriterio = $evaluacionCriterio->id;
							$hallazgo->idPlazoAccion = $plazoAccion[$key];
							
							$hallazgo->save();
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
			return Response::json(array("status"=>201,"messages"=>"Creado","value"=>$evaluacion),201);
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
		$evaluacion = Evaluacion::with("Padres")->find($id);

		if(!$evaluacion)
		{
			return Response::json(array('status'=> 404,"messages"=>'No encontrado'),404);
		} 
		else 
		{
			return Response::json(array("status"=>200,"messages"=>"ok","value"=>$evaluacion),200);
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
			$evaluacion = Evaluacion::find($id);
			$evaluacion->nombre = $datos->get('nombre');
			$evaluacion->idPadre = $datos->get('idPadre');
			$evaluacion->url = $datos->get('url');
			$evaluacion->icon = $datos->get('icon');
			$evaluacion->controlador = $datos->get('controlador');
			$evaluacion->vista = $datos->get('vista');

            if ($evaluacion->save()) 
                $success = true;
		} 
		catch (\Exception $e) 
		{
        }
        if ($success)
		{
			DB::commit();
			return Response::json(array("status"=>200,"messages"=>"ok","value"=>$evaluacion),200);
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
        }
        if ($success)
		{
			DB::commit();
			return Response::json(array("status"=>200,"messages"=>"ok","value"=>$evaluacion),200);
		} 
		else 
		{
			DB::rollback();
			return Response::json(array('status'=> 500,"messages"=>'Error interno del servidor'),500);
		}
	}
}
?>