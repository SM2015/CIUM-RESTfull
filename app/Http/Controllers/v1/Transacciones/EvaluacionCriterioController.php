<?php namespace App\Http\Controllers\v1\Transacciones;

use App\Http\Requests;
use App\Http\Controllers\Controller;

use Response;
use Input;
use DB;
use Sentry;
use Request;
use App\Models\Transacciones\EvaluacionCriterio;
use App\Models\Transacciones\EvaluacionCriterioCriterio;
use App\Models\Transacciones\Hallazgo;
use App\Models\Transacciones\Seguimiento;
use App\Models\Catalogos\Accion;

class EvaluacionCriterioCriterioController extends Controller 
{	
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
				$evaluacionCriterio = EvaluacionCriterio::where($columna, 'LIKE', '%'.$valor.'%')->skip($pagina-1)->take($datos->get('limite'))->get();
			}
			else
			{
				$evaluacionCriterio = EvaluacionCriterio::skip($pagina-1)->take($datos['limite'])->get();
			}
			$total=EvaluacionCriterio::get();
		}
		else
		{
			$evaluacionCriterio = EvaluacionCriterio::get();
			$total=$evaluacionCriterio;
		}

		if(!$evaluacionCriterio)
		{
			return Response::json(array('status'=> 404,"messages"=>'No encontrado'),404);
		} 
		else 
		{
			return Response::json(array("status"=>200,"messages"=>"ok","data"=>$evaluacionCriterio,"total"=>count($total)),200);
			
		}
	}

	/**
	 * Store a newly created resource in storage.
	 *
	 * @return Response
	 */
	public function store()
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
			$evaluacionCriterio->aprobado = $datos->get('aprobado');
			
            if ($evaluacionCriterio->save()) 
			{
				$borrado = DB::table('hallazgo')					
				->where('idEvaluacionCriterio',$evaluacionCriterio->id)
				->update(['borradoAL' => NULL]);
			
				$hallazgo = Hallazgo::where('idEvaluacionCriterio',$evaluacionCriterio->id)->first();
				
				if(!$hallazgo)
					$hallazgo = new Hallazgo;				
				
				if($datos->get('aprobado')==0)
				{
					if($datos->get('accionx'))
					{
						$hallazgo->idUsuario = $usuario->id;
						$hallazgo->idAccion = $datos->get('accionx');
						$hallazgo->idEvaluacionCriterio = $evaluacionCriterio->id;
						$hallazgo->idPlazoAccion = $datos->get('plazoAccionx');
						$hallazgo->resuelto = $datos->get('resueltox');
						$hallazgo->descripcion = $datos->get('hallazgox');
						$hallazgo->cuantitativo = $datos->get('cuantitativox');
						$hallazgo->cantidad = $datos->get('cantidadx');
											
						$accion = Accion::find($datos->get('accionx'));
						
						$borrado = DB::table('seguimiento')							
						->where('idHallazgo',$hallazgo->id)
						->update(['borradoAL' => NULL]);
						
						$hallazgo->resuelto = 0;
						$seguimiento = Seguimiento::where("idHallazgo",$hallazgo->id)->first();
						if($accion->tipo == "R")
						{
							$hallazgo->resuelto = 1;							
							if($seguimiento)
								$seguimiento->delete();
						}
						
						$hallazgo->save();
						if($accion->tipo == "S")
						{							
							if(!$seguimiento)
								$seguimiento = new Seguimiento;
							
							$seguimiento->idUsuario = $usuario->id;
							$seguimiento->idHallazgo = $hallazgo->id;
							$seguimiento->descripcion = "Inicia seguimiento al hallazgo ".$hallazgo->descripcion;
							
							$seguimiento->save();
						}
					}
				}
				else
				{
					if($hallazgo->id)
					{
						$hallazgo = Hallazgo::find($hallazgo->id);
						$hallazgo->delete();
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
			return Response::json(array("status"=>201,"messages"=>"Creado","data"=>$evaluacionCriterio),201);
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
		$evaluacionCriterio = DB::table('evaluacionCriterio AS e')
			->leftJoin('clues AS c', 'c.clues', '=', 'e.clues')
			->leftJoin('ConeClues AS cc', 'cc.clues', '=', 'e.clues')
			->leftJoin('cone AS co', 'co.id', '=', 'cc.idCone')
            ->select(array('e.fechaEvaluacionCriterio','e.id','e.clues', 'c.nombre', 'c.domicilio', 'c.codigoPostal', 'c.entidad', 'c.municipio', 'c.localidad', 'c.jurisdiccion', 'c.institucion', 'c.tipoUnidad', 'c.estatus', 'c.estado', 'c.tipologia','co.nombre as nivelCone', 'cc.idCone'))
            ->where('e.id',"$id")
			->first();

		if(!$evaluacionCriterio)
		{
			return Response::json(array('status'=> 404,"messages"=>'No encontrado'),404);
		} 
		else 
		{
			return Response::json(array("status"=>200,"messages"=>"ok","data"=>$evaluacionCriterio),200);
		}
	}
}
?>