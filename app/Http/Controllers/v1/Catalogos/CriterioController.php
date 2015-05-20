<?php namespace App\Http\Controllers\v1\Catalogos;

use App\Http\Requests;
use App\Http\Controllers\Controller;

use Request;
use Response;
use Input;
use DB;
use App\Models\Catalogos\Criterio;
use App\Models\Catalogos\IndicadorCriterio;
use App\Models\Catalogos\ConeIndicadorCriterio;
use App\Models\Catalogos\LugarVerificacion;

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
				$criterio = Criterio::with("Indicadores")->where($columna, 'LIKE', '%'.$valor.'%')->skip($pagina-1)->take($datos->get('limite'))->get();
			}
			else
			{
				$criterio = Criterio::with("Indicadores")->skip($pagina-1)->take($datos['limite'])->get();
			}
			$total=Criterio::with("Indicadores")->get();
		}
		else
		{
			$criterio = Criterio::with("Indicadores")->get();					
			$total=$criterio;
		}

		if(!$criterio)
		{
			return Response::json(array('status'=> 404,"messages"=>'No encontrado'),404);
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
					->where('ci.idIndicadorCriterio' , $indicador->id )
					->get();
					
					$pivot = json_encode($indicador->pivot);
					$pivot = (array)json_decode($pivot);
										
					$indicador->lugarVerificacion=lugarVerificacion::find($pivot["idLugarVerificacion"]);																				
				}						
			}
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
			if ($criterio->save()) 
			{
				$indicadores = $datos->get('indicadores');
				
				foreach($indicadores as $i)
				{
					$indicador = IndicadorCriterio::where("idCriterio", $criterio->id)->where("idIndicador", $i["id"])->first();
					if(!$indicador)
						$indicador = new IndicadorCriterio;
					$indicador->idCriterio = $criterio->id;
					$indicador->idIndicador = $i["id"];
					$indicador->idLugarVerificacion = $i["idlugarVerificacion"];
					
					if ($indicador->save()) 
					{
						foreach($i["cones"] as $c)
						{
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
		$criterio = Criterio::with("Indicadores")->find($id);

		if(!$criterio)
		{
			return Response::json(array('status'=> 404,"messages"=>'No encontrado'),404);
		} 
		else 
		{
			
			foreach($criterio["indicadores"] as $indicador)
			{				
				$indicador->cones=DB::table('ConeIndicadorCriterio AS ci') 
				->leftJoin('Cone AS c', 'c.id', '=', 'ci.idCone')
				->select("*")
				->where('ci.idIndicadorCriterio' , $indicador->id )
				->get();
				
				$pivot = json_encode($indicador->pivot);
				$pivot = (array)json_decode($pivot);
									
				$indicador->lugarVerificacion=lugarVerificacion::find($pivot["idLugarVerificacion"]);																								
			}
				
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
			
            if ($criterio->save()) 
			{
				$indicadores = $datos->get('indicadores');
				
				foreach($indicadores as $i)
				{
					$indicador = IndicadorCriterio::where("idCriterio", $criterio->id)->where("idIndicador", $i["id"])->first();
					if(!$indicador)
						$indicador = new IndicadorCriterio;
					$indicador->idCriterio = $criterio->id;
					$indicador->idIndicador = $i["id"];
					$indicador->idLugarVerificacion = $i["idlugarVerificacion"];
					
					if ($indicador->save()) 
					{
						foreach($i["cones"] as $c)
						{
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
		
		$criterio = DB::select("SELECT c.id as idCriterio, ic.idIndicador, cic.idCone, lv.id as idlugarVerificacion, c.creadoAl, c.modificadoAl, c.nombre as criterio, lv.nombre as lugarVerificacion FROM ConeIndicadorCriterio cic							
		left join IndicadorCriterio ic on ic.id = cic.idIndicadorCriterio
		left join Criterio c on c.id = ic.idCriterio
		left join LugarVerificacion lv on lv.id = ic.idlugarVerificacion		
		WHERE cic.idCone = $cone and ic.idIndicador = $indicador");	
		
		//$criterio = Criterio::with("LugarVerificaciones")->where('idCone', '=', $cone )->where('idIndicador', '=', $indicador)->orderBy('idLugarVerificacion', 'ASC')->get();
				
		$evaluacionCriterio = EvaluacionCriterio::where('idEvaluacion',$evaluacion)->get();
		$aprobado=array();
		$noAplica=array();
		$noAprobado=array();
		
		$hallazgo=array();
		foreach($evaluacionCriterio as $valor)
		{
			if($valor->aprobado == '1')
			{
				array_push($aprobado,$valor->idCriterio);
			}
			else if($valor->aprobado == '2')
			{
				array_push($noAplica,$valor->idCriterio);
			}
			else
			{	
				array_push($noAprobado,$valor->idCriterio);				
			}
		}
		$criterio["noAplica"] = $noAplica;
		$criterio["aprobado"] = $aprobado;
		$criterio["noAprobado"] = $noAprobado;
		
		$result = DB::select("SELECT h.idLugarVerificacion, h.idAccion, h.idPlazoAccion, h.resuelto, h.descripcion, a.tipo FROM Hallazgo h	
		left join Accion a on a.id = h.idAccion WHERE h.idEvaluacion = $evaluacion ");
			
		if($result)
		{
			foreach($result as $r)
			{
				$hallazgo[$r->idLugarVerificacion] = $r;
			}
		}
			
		$criterio["hallazgo"] = $hallazgo;
		
		
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
		$evaluacionC = DB::table('evaluacion AS e')
			->leftJoin('clues AS c', 'c.clues', '=', 'e.clues')
			->leftJoin('ConeClues AS cc', 'cc.clues', '=', 'e.clues')
			->leftJoin('cone AS co', 'co.id', '=', 'cc.idCone')
            ->select(array('e.fechaEvaluacion', 'e.cerrado', 'e.id','e.clues', 'c.nombre', 'c.domicilio', 'c.codigoPostal', 'c.entidad', 'c.municipio', 'c.localidad', 'c.jurisdiccion', 'c.institucion', 'c.tipoUnidad', 'c.estatus', 'c.estado', 'c.tipologia','co.nombre as nivelCone', 'cc.idCone'))
            ->where('e.id',"$evaluacion")
			->first();
			
		$cone = $evaluacionC->idCone;
		$aprobado=array();
		$noAplica=array();
		$noAprobado=array();
		
		$hallazgo=array();
		$criterio = [];
		$indicadores = [];
		foreach($evaluacionCriterio as $valor)
		{
			$indicador = DB::select("SELECT idIndicador FROM IndicadorCriterio ic 
			left join ConeIndicadorCriterio cic on cic.idCone = '$cone'
			where ic.idCriterio = '$valor->idCriterio' and idIndicador = '$valor->idIndicador'");
			$indicador = $indicador[0]->idIndicador;
			
			$result = DB::select("SELECT i.codigo, i.nombre,c.id as idCriterio, ic.idIndicador, cic.idCone, lv.id as idlugarVerificacion, c.creadoAl, c.modificadoAl, c.nombre as criterio, lv.nombre as lugarVerificacion FROM ConeIndicadorCriterio cic							
			left join IndicadorCriterio ic on ic.id = cic.idIndicadorCriterio
			left join Criterio c on c.id = ic.idCriterio
			left join Indicador i on i.id = ic.idIndicador
			left join LugarVerificacion lv on lv.id = ic.idlugarVerificacion		
			WHERE cic.idCone = $cone and ic.idIndicador = $indicador and c.id = $valor->idCriterio ");						
			
			if($valor->aprobado == '1')
			{
				array_push($aprobado,$valor->idCriterio);
			}
			else if($valor->aprobado == '2')
			{
				array_push($noAplica,$valor->idCriterio);
			}
			else
			{
				array_push($noAprobado,$valor->idCriterio);								
			}
			$result[0]->aprobado=$valor->aprobado;
			array_push($criterio,$result[0]);				
		}
		$result = DB::select("SELECT h.idLugarVerificacion, h.idAccion, h.idPlazoAccion, h.resuelto, h.descripcion, a.tipo FROM Hallazgo h	
		left join Accion a on a.id = h.idAccion WHERE h.idEvaluacion = $evaluacion ");
			
		if($result)
		{
			foreach($result as $r)
			{
				$hallazgo[$r->idLugarVerificacion] = $r;
			}
		}
		foreach($criterio as $item)
		{
			if(!array_key_exists($item->codigo,$indicadores))
			{
				$id = $item->idIndicador;
				
				$total = DB::select("SELECT c.id,c.nombre  FROM ConeIndicadorCriterio cic							
						left join IndicadorCriterio ic on ic.id = cic.idIndicadorCriterio
						left join Criterio c on c.id = ic.idCriterio
						left join Indicador i on i.id = ic.idIndicador
						left join LugarVerificacion lv on lv.id = ic.idlugarVerificacion		
						WHERE cic.idCone = $cone and ic.idIndicador = $id");
				//DB::table('Criterio')->select('id')->where('idIndicador',$id)->where('idCone',$criterio[0]->idCone)->get();
				$in=[];
				foreach($total as $c)
				{
					$in[]=$c->id;
				}
				
				$aprobado = DB::table('EvaluacionCriterio')->select('idCriterio')->whereIN('idCriterio',$in)->where('idEvaluacion',$evaluacion)->where('aprobado',1)->get();				
				$na = DB::table('EvaluacionCriterio')->select('idCriterio')->whereIN('idCriterio',$in)->where('idEvaluacion',$evaluacion)->where('aprobado',2)->get();				
				
				$item->indicadores["totalCriterios"] = count($total)-count($na);
				$item->indicadores["totalAprobados"] = count($aprobado);
				$item->indicadores["totalPorciento"] = number_format((count($aprobado)/(count($total)-count($na)))*100, 2, '.', '');
				
				$indicadores[$item->codigo] = $item;				
			}				
		}
		$criterio["noAplica"] = $noAplica;
		$criterio["aprobado"] = $aprobado;
		$criterio["noAprobado"] = $noAprobado;
		$criterio["hallazgo"] = $hallazgo;	
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
		$evaluacionCriterio = EvaluacionCriterio::with('Evaluaciones')->where('idEvaluacion',$evaluacion)->get(array('idCriterio','aprobado','id','idIndicador'));
		
		$indicador = [];
		
		foreach($evaluacionCriterio as $item)
		{
			$sql = "SELECT distinct i.id, i.codigo, i.nombre, (SELECT count(id) FROM ConeIndicadorCriterio where idIndicadorCriterio in(select id from IndicadorCriterio where  idIndicador=ci.idIndicador) and idCone=cc.idCone) as total 
			FROM ConeClues cc 
			left join ConeIndicadorCriterio cic on cic.idCone = cc.idCone
			left join IndicadorCriterio ci on ci.id = cic.idIndicadorCriterio 
            left join Indicador i on i.id = ci.idIndicador
            where cc.clues = '$clues' and ci.idCriterio = $item->idCriterio and ci.idIndicador = $item->idIndicador and i.id is not null";
			
			$result = DB::select($sql);
			if($result)
			{
				$result = (array)$result[0];
				$existe = false; $contador=0;
				for($i=0;$i<count($indicador);$i++)
				{
					if(array_key_exists($result["codigo"],$indicador[$i]))
					{						
						$indicador[$i][$result["codigo"]]=$indicador[$i][$result["codigo"]]+1;						
						$existe = true;
					}
				}
			}
			if(!$existe)
			{
				$contador=1;
				
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
	/*
	public function operacion()
	{
		
		$criterio = Criterio::get();
		foreach($criterio as $item)
		{
			$success = false;
			DB::beginTransaction();
			try 
			{
				$dato = DB::select("select idIndicador,idLugarVerificacionCriterio from ciumAnterior.Criterio where nombre = '$item->nombre'");	
				$indica = $dato[0]->idIndicador;
				$indicador = new IndicadorCriterio;
				$indicador->idCriterio = $item->id;
				$indicador->idIndicador = $indica;
				$indicador->idLugarVerificacion = $dato[0]->idLugarVerificacionCriterio;
				
				if (!$indicador->save()) 
				{
					echo "error".$item->id.",";
					
				}
				else{
					$datoC = DB::select("select idCone from ciumAnterior.Criterio where nombre = '$item->nombre' and idIndicador='$indica'");	
					
					$cone = new ConeIndicadorCriterio;
					$cone->idIndicadorCriterio = $indicador->id;
					$cone->idCone = $datoC[0]->idCone;
					
					if (!$cone->save()) 
					{
						echo "error indicador ".$dato[0]->idIndicador.",";						
					}
					else $success = true;
				}			
			} 
			catch (\Exception $e) 
			{
				throw $e;
			}
			if ($success)
			{
				echo "bien";
				DB::commit();
			} 
			else 
			{
				echo "mal";
				DB::rollback();
			}
		}	
	}*/
}
