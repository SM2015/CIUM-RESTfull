<?php namespace App\Http\Controllers\v1\Transacciones;

use App\Http\Requests;
use App\Http\Controllers\Controller;

use Request;
use Response;
use Input;
use DB;
use Sentry;

use App\Models\Transacciones\Seguimiento;
use App\Models\Transacciones\Hallazgo;
use App\Models\Transacciones\Evaluacion;
use App\Models\Catalogos\Accion;
use App\Models\Catalogos\Criterio;
use App\Http\Requests\SeguimientoRequest;
 

class SeguimientoController extends Controller {

	/**
	 * Display a listing of the resource.
	 *
	 * @return Response
	 */
	public function index()
	{
		
		$datos = Request::all();
		$accion = Accion::where("tipo","S")->get(array("id"))->toArray(); 		
		
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
				$seguimiento = Hallazgo::with("Usuario","EvaluacionCriterio","Accion","Plazo")->where($columna, 'LIKE', '%'.$valor.'%')->whereIn("idAccion",$accion)->skip($pagina-1)->take($datos->get('limite'))->get();
			}
			else
			{
				$seguimiento = Hallazgo::with("Usuario","EvaluacionCriterio","Accion","Plazo")->whereIn("idAccion",$accion)->skip($pagina-1)->take($datos['limite'])->get();
			}
			$total=Hallazgo::with("Usuario","EvaluacionCriterio","Accion","Plazo")->whereIn("idAccion",$accion)->get();
		}
		else
		{
			$seguimiento = Hallazgo::with("Usuario","EvaluacionCriterio","Accion","Plazo")->whereIn("idAccion",$accion)->get();
			$total=$seguimiento;
		}
		
		$i=0;
		foreach($seguimiento as $item)
		{	
			$seguimiento[$i]["evaluacion"] = Evaluacion::where("id",$item->EvaluacionCriterio["idEvaluacion"])->get(array("clues","id"))->first();
			$seguimiento[$i]["criterio"] = Criterio::where("id",$item->EvaluacionCriterio["idCriterio"])->get(array("nombre"))->first();	
			$i++;
		}

		if(!$seguimiento)
		{
			return Response::json(array('status'=> 404,"messages"=>'No encontrado'),404);
		} 
		else 
		{
			return Response::json(array("status"=>200,"messages"=>"ok","data"=>$seguimiento,"total"=>count($total)),200);
			
		}
	}

	/**
	 * Store a newly created resource in storage.
	 *
	 * @return Response
	 */
	public function store(SeguimientoRequest $request)
	{
		$datos = Input::json();
		$success = false;
        DB::beginTransaction();
        try 
		{
			$usuario = Sentry::getUser();
			
            $seguimiento = new Seguimiento;
            $seguimiento->idUsuario = $usuario->id;
			$seguimiento->idHallazgo = $datos->get('idHallazgo');
			$seguimiento->descripcion = $datos->get('descripcion');

            if ($seguimiento->save()) 
                $success = true;
        } 
		catch (\Exception $e) 
		{
			throw $e;
        }
        if ($success) 
		{
            DB::commit();
			return Response::json(array("status"=>201,"messages"=>"Creado","data"=>$seguimiento),201);
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
		$seguimiento = Hallazgo::with("Usuario","EvaluacionCriterio","Accion","Plazo")->find($id);
				
		$id=$seguimiento->EvaluacionCriterio["idEvaluacion"];
		$seguimiento["evaluacion"] = DB::table('Evaluacion AS e')
		->leftJoin('Clues AS c', 'c.clues', '=', 'e.clues')
		->leftJoin('ConeClues AS cc', 'cc.clues', '=', 'e.clues')
		->leftJoin('Cone AS co', 'co.id', '=', 'cc.idCone')
		->select(array('e.fechaEvaluacion', 'e.cerrado', 'e.id','e.clues', 'c.nombre', 'c.domicilio', 'c.codigoPostal', 'c.entidad', 'c.municipio', 'c.localidad', 'c.jurisdiccion', 'c.institucion', 'c.tipoUnidad', 'c.estatus', 'c.estado', 'c.tipologia','co.nombre as nivelCone', 'cc.idCone'))
		->where('e.id',"$id")
		->first();
		$seguimiento["criterio"] = Criterio::where("id",$seguimiento->EvaluacionCriterio["idCriterio"])->get(array("nombre"))->first();	
		
		$seguimiento["seguimiento"] = Seguimiento::with("usuario")->where("idHallazgo",$seguimiento->id)->get();
		
		if(!$seguimiento)
		{
			return Response::json(array('status'=> 404,"messages"=>'No encontrado'),404);
		} 
		else 
		{
			return Response::json(array("status"=>200,"messages"=>"ok","data"=>$seguimiento),200);
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
			$seguimiento = Hallazgo::find($id);
			
			$seguimiento->idUsuario = $usuario->id;
			$seguimiento->resuelto = $datos->get('resuelto');

            if ($seguimiento->save()) 
                $success = true;
		} 
		catch (\Exception $e) 
		{
        }
        if ($success)
		{
			DB::commit();
			return Response::json(array("status"=>200,"messages"=>"ok","data"=>$seguimiento),200);
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
			$seguimiento = Seguimiento::find($id);
			$seguimiento->delete();
			$success=true;
		} 
		catch (\Exception $e) 
		{
        }
        if ($success)
		{
			DB::commit();
			return Response::json(array("status"=>200,"messages"=>"ok","data"=>$seguimiento),200);
		} 
		else 
		{
			DB::rollback();
			return Response::json(array('status'=> 500,"messages"=>'Error interno del servidor'),500);
		}
	}
	
	/**
	 * Display a listing of the resource.
	 *
	 * @return Response
	 */
	public function SeguimientoEvaluacion($cone,$indicador)
	{		
		$datos = Request::all();
		
		$evaluacion = $datos['evaluacion'];
				
		$seguimiento = Seguimiento::with("LugarVerificacionSeguimientos")->where('idCone', '=', $cone )->where('idIndicador', '=', $indicador)->orderBy('idLugarVerificacionSeguimiento', 'ASC')->get();
				
		$evaluacionSeguimiento = EvaluacionSeguimiento::where('idEvaluacion',$evaluacion)->get();
		$ec=array();
		$eh=array();
		foreach($evaluacionSeguimiento as $valor)
		{
			if($valor->aprobado == '1')
				array_push($ec,$valor->idSeguimiento);
			else
			{				
				$result = DB::select("SELECT h.idAccion, h.idPlazoAccion, h.resuelto, h.descripcion, h.cuantitativo, cantidad FROM Hallazgo h							
				left join Seguimiento c on c.id = $valor->idSeguimiento				
				WHERE h.idEvaluacionSeguimiento = $valor->id and c.idIndicador = $indicador");
				
				if($result)
				{
					$result = (array)$result[0];
					$eh[$valor->idSeguimiento] = $result;
				}
			}
		}
		$seguimiento["evaluacion"] = $ec;
		$seguimiento["hallazgo"] = $eh;
		
		
		if(!$seguimiento)
		{
			return Response::json(array('status'=> 404,"messages"=>'No encontrado'),404);
		} 
		else 
		{
			return Response::json(array("status"=>200,"messages"=>"ok","data"=>$seguimiento,"total"=>count($seguimiento)),200);
			
		}
	}
	
	/**
	 * Display a listing of the resource.
	 *
	 * @return Response
	 */
	public function SeguimientoEvaluacionVer($evaluacion)
	{		
		$evaluacionSeguimiento = EvaluacionSeguimiento::with('Evaluaciones')->where('idEvaluacion',$evaluacion)->get();
		
		$ec=array();
		$eh=array();
		$seguimiento = [];
		foreach($evaluacionSeguimiento as $valor)
		{
			$result = Seguimiento::with("LugarVerificacionSeguimientos")->find($valor->idSeguimiento);
			array_push($seguimiento,$result);	
			if($valor->aprobado == '1')
				array_push($ec,$valor->idSeguimiento);
			else
			{				
				$result = DB::select("SELECT h.idAccion, h.idPlazoAccion, h.resuelto, h.descripcion, h.cuantitativo, cantidad FROM Hallazgo h							
				left join Seguimiento c on c.id = $valor->idSeguimiento				
				WHERE h.idEvaluacionSeguimiento = $valor->id ");
				
				if($result)
				{
					$result = (array)$result[0];
					$eh[$valor->idSeguimiento] = $result;
				}
			}
		}
		
		$seguimiento["evaluacion"] = $ec;
		$seguimiento["hallazgo"] = $eh;
		
		if(!$seguimiento)
		{
			return Response::json(array('status'=> 200,"messages"=>'ok', "data"=> []),200);
		} 
		else 
		{
			return Response::json(array("status"=>200,"messages"=>"ok","data"=>$seguimiento),200);			
		}
	}
	
	/**
	 * Display a listing of the resource.
	 *
	 * @return Response
	 */
	public function Estadistica($evaluacion)
	{		
		$clues = Evaluacion::find($evaluacion)->first()->clues;
		$evaluacionSeguimiento = EvaluacionSeguimiento::with('Evaluaciones')->where('idEvaluacion',$evaluacion)->get(array('idSeguimiento','aprobado','id'));
		
		$indicador = [];
		
		foreach($evaluacionSeguimiento as $item)
		{
			$sql = "SELECT i.id, i.codigo, i.nombre, (select count(idIndicador) from Seguimiento where idIndicador = i.id and idCone = cc.idCone) as total FROM Seguimiento c 
			left join Indicador i on i.id = c.idIndicador 
			left join ConeClues cc on cc.clues = '$clues'
			WHERE c.id=$item->idSeguimiento";
		
			$result = DB::select($sql);
			$result = (array)$result[0];
			$existe = false; $contador=0;
			for($i=0;$i<count($indicador);$i++)
			{
				if(array_key_exists($result["codigo"],$indicador[$i]))
				{
					if($item->aprobado == '0')
					{
						$hallazgo = Hallazgo::where("idEvaluacionSeguimiento",$item->id)->first();
						if($hallazgo)
							$indicador[$i][$result["codigo"]]=$indicador[$i][$result["codigo"]]+1;							
					}
					else
						$indicador[$i][$result["codigo"]]=$indicador[$i][$result["codigo"]]+1;
					
					$existe = true;
				}
			}
			if(!$existe)
			{
				if($item->aprobado == '0')
				{
					$hallazgo = Hallazgo::where("idEvaluacionSeguimiento",$item->id)->get();
					
					if(!$hallazgo)
						$contador = 1;
				}				
				$result[$result["codigo"]] = $contador == 1 ? 0 : 1;
				array_push($indicador,$result);
			}
		}
		
		if(!$indicador)
		{
			return Response::json(array('status'=> 200,"messages"=>'ok', "data"=> []),200);
		} 
		else 
		{
			return Response::json(array("status"=>200,"messages"=>"ok","data"=>$indicador),200);			
		}
	}
}
