<?php namespace App\Http\Controllers\v1\Catalogos;

use App\Http\Requests;
use App\Http\Controllers\Controller;

use Request;
use Response;
use Input;
use DB;
use App\Models\Catalogos\Criterio;
use App\Models\Transacciones\Evaluacion;
use App\Models\Transacciones\EvaluacionCriterio;
use App\Models\Transacciones\Hallazgo;
use App\Http\Requests\CriterioRequest;
 

class CriterioController extends Controller {

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
				$criterio = Criterio::with("Indicadores","Cones","LugarVerificacionCriterios")->where($columna, 'LIKE', '%'.$valor.'%')->skip($pagina-1)->take($datos->get('limite'))->get();
			}
			else
			{
				$criterio = Criterio::with("Indicadores","Cones","LugarVerificacionCriterios")->skip($pagina-1)->take($datos['limite'])->get();
			}
			$total=Criterio::with("Indicadores","Cones","LugarVerificacionCriterios")->get();
		}
		else
		{
			$criterio = Criterio::with("Indicadores","Cones","LugarVerificacionCriterios")->get();
			$total=$criterio;
		}

		if(!$criterio)
		{
			return Response::json(array('status'=> 404,"messages"=>'No encontrado'),404);
		} 
		else 
		{
			return Response::json(array("status"=>200,"messages"=>"ok","data"=>$criterio,"total"=>count($total)),200);
			
		}
	}

	/**
	 * Store a newly created resource in storage.
	 *
	 * @return Response
	 */
	public function store(CriterioRequest $request)
	{
		$datos = Input::json();
		$success = false;
        DB::beginTransaction();
        try 
		{
            $criterio = new Criterio;
            $criterio->nombre = $datos->get('nombre');
			$criterio->idIndicador = $datos->get('idIndicador');
			$criterio->idCone = $datos->get('idCone');
			$criterio->idLugarVerificacionCriterio = $datos->get('idLugarVerificacionCriterio');

            if ($criterio->save()) 
                $success = true;
        } 
		catch (\Exception $e) 
		{
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
	 * Display the specified resource.
	 *
	 * @param  int  $id
	 * @return Response
	 */
	public function show($id)
	{
		$criterio = Criterio::with("Indicadores","Cones","LugarVerificacionCriterios")->find($id);

		if(!$criterio)
		{
			return Response::json(array('status'=> 404,"messages"=>'No encontrado'),404);
		} 
		else 
		{
			return Response::json(array("status"=>200,"messages"=>"ok","data"=>$criterio),200);
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
			$criterio = Criterio::find($id);
			$criterio->nombre = $datos->get('nombre');
			$criterio->idIndicador = $datos->get('idIndicador');
			$criterio->idCone = $datos->get('idCone');
			$criterio->idLugarVerificacionCriterio = $datos->get('idLugarVerificacionCriterio');

            if ($criterio->save()) 
                $success = true;
		} 
		catch (\Exception $e) 
		{
        }
        if ($success)
		{
			DB::commit();
			return Response::json(array("status"=>200,"messages"=>"ok","data"=>$criterio),200);
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
			$criterio = Criterio::find($id);
			$criterio->delete();
			$success=true;
		} 
		catch (\Exception $e) 
		{
        }
        if ($success)
		{
			DB::commit();
			return Response::json(array("status"=>200,"messages"=>"ok","data"=>$criterio),200);
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
	public function CriterioEvaluacion($cone,$indicador)
	{		
		$datos = Request::all();
		
		$evaluacion = $datos['evaluacion'];
				
		$criterio = Criterio::with("LugarVerificacionCriterios")->where('idCone', '=', $cone )->where('idIndicador', '=', $indicador)->orderBy('idLugarVerificacionCriterio', 'ASC')->get();
				
		$evaluacionCriterio = EvaluacionCriterio::where('idEvaluacion',$evaluacion)->get();
		$ec=array();
		$eh=array();
		foreach($evaluacionCriterio as $valor)
		{
			if($valor->aprobado == '1')
				array_push($ec,$valor->idCriterio);
			else
			{				
				$result = DB::select("SELECT h.idAccion, h.idPlazoAccion, h.resuelto, h.descripcion, h.cuantitativo, cantidad FROM Hallazgo h							
				left join Criterio c on c.id = $valor->idCriterio				
				WHERE h.idEvaluacionCriterio = $valor->id and c.idIndicador = $indicador");
				
				if($result)
				{
					$result = (array)$result[0];
					$eh[$valor->idCriterio] = $result;
				}
			}
		}
		$criterio["evaluacion"] = $ec;
		$criterio["hallazgo"] = $eh;
		
		
		if(!$criterio)
		{
			return Response::json(array('status'=> 404,"messages"=>'No encontrado'),404);
		} 
		else 
		{
			return Response::json(array("status"=>200,"messages"=>"ok","data"=>$criterio,"total"=>count($criterio)),200);
			
		}
	}
	
	/**
	 * Display a listing of the resource.
	 *
	 * @return Response
	 */
	public function CriterioEvaluacionVer($evaluacion)
	{		
		$evaluacionCriterio = EvaluacionCriterio::with('Evaluaciones')->where('idEvaluacion',$evaluacion)->get();
		
		$ec=array();
		$eh=array();
		$criterio = [];
		$indicadores = [];
		foreach($evaluacionCriterio as $valor)
		{
			$result = Criterio::with("LugarVerificacionCriterios","Indicadores")->find($valor->idCriterio);
			array_push($criterio,$result);	
			if($valor->aprobado == '1')
				array_push($ec,$valor->idCriterio);
			else
			{				
				$hallazgo = DB::select("SELECT h.idAccion, h.idPlazoAccion, h.resuelto, h.descripcion, h.cuantitativo, cantidad FROM Hallazgo h							
				left join Criterio c on c.id = $valor->idCriterio				
				WHERE h.idEvaluacionCriterio = $valor->id ");
				
				if($hallazgo)
				{
					$hallazgo = (array)$hallazgo[0];
					$eh[$valor->idCriterio] = $hallazgo;
				}
			}									
		}
		foreach($criterio as $item)
		{
			if(!array_key_exists($item->indicadores["codigo"],$indicadores))
			{
				$id = $item->indicadores["id"];
				
				$total = DB::table('Criterio')->select('id')->where('idIndicador',$id)->where('idCone',$criterio[0]->idCone)->get();
				$in=[];
				foreach($total as $c)
				{
					$in[]=$c->id;
				}
				
				$aprobado = DB::table('EvaluacionCriterio')->select('idCriterio')->whereIN('idCriterio',$in)->where('idEvaluacion',$evaluacion)->where('aprobado',1)->get();				
				
				$item->indicadores["totalCriterios"] = count($total);
				$item->indicadores["totalAprobados"] = count($aprobado);
				$item->indicadores["totalPorciento"] = number_format((count($aprobado)/count($total))*100, 2, '.', '');
				
				$indicadores[$item->indicadores["codigo"]] = $item->indicadores;				
			}				
		}
		$criterio["evaluacion"] = $ec;
		$criterio["hallazgo"] = $eh;	
		$criterio["indicadores"] = $indicadores;
		
		if(!$criterio)
		{
			return Response::json(array('status'=> 200,"messages"=>'ok', "data"=> []),200);
		} 
		else 
		{
			return Response::json(array("status"=>200,"messages"=>"ok","data"=>$criterio),200);			
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
		$evaluacionCriterio = EvaluacionCriterio::with('Evaluaciones')->where('idEvaluacion',$evaluacion)->get(array('idCriterio','aprobado','id'));
		
		$indicador = [];
		
		foreach($evaluacionCriterio as $item)
		{
			$sql = "SELECT i.id, i.codigo, i.nombre, (select count(idIndicador) from Criterio where idIndicador = i.id and idCone = cc.idCone) as total FROM Criterio c 
			left join Indicador i on i.id = c.idIndicador 
			left join ConeClues cc on cc.clues = '$clues'
			WHERE c.id=$item->idCriterio";
		
			$result = DB::select($sql);
			$result = (array)$result[0];
			$existe = false; $contador=0;
			for($i=0;$i<count($indicador);$i++)
			{
				if(array_key_exists($result["codigo"],$indicador[$i]))
				{
					if($item->aprobado == '0')
					{
						$hallazgo = Hallazgo::where("idEvaluacionCriterio",$item->id)->first();
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
					$hallazgo = Hallazgo::where("idEvaluacionCriterio",$item->id)->get();
					
					if($hallazgo)
						$contador = 0;
				}				
				else $contador=1;
				
				$result[$result["codigo"]] = $contador;
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
