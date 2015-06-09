<?php namespace App\Http\Controllers\v1\Transacciones;

use App\Http\Requests;
use App\Http\Controllers\Controller;

use Response;
use Input;
use DB;
use Sentry;
use Request;

use App\Models\Catalogos\IndicadorAlerta;
use App\Models\Catalogos\IndicadorCriterio;
use App\Models\Catalogos\ConeIndicadorCriterio;
use App\Models\Catalogos\LugarVerificacion;

use App\Models\Transacciones\EvaluacionCalidad;
use App\Models\Transacciones\EvaluacionCalidadCriterio;
use App\Models\Transacciones\EvaluacionCalidadRegistro;

use App\Models\Transacciones\Hallazgo;
use App\Models\Transacciones\Seguimiento;
use App\Models\Catalogos\Accion;

class EvaluacionCalidadCriterioController extends Controller 
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
				$evaluacionCriterio = EvaluacionCalidadCriterio::where($columna, 'LIKE', '%'.$valor.'%')->skip($pagina-1)->take($datos->get('limite'))->get();
			}
			else
			{
				$evaluacionCriterio = EvaluacionCalidadCriterio::skip($pagina-1)->take($datos['limite'])->get();
			}
			$total=EvaluacionCalidadCriterio::get();
		}
		else
		{
			$evaluacionCriterio = EvaluacionCalidadCriterio::get();
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
			$evaluacionCriterio = EvaluacionCalidadCriterio::where('idEvaluacionCalidad',$datos->get('idEvaluacionCalidad'))->where('idCriterio',$datos->get('idCriterio'))->first();
				
			if(!$evaluacionCriterio)
				$evaluacionCriterio = new EvaluacionCalidadCriterio;
			
            $evaluacionCriterio->idEvaluacionCalidad = $datos->get('idEvaluacionCalidad');
			$evaluacionCriterio->idCriterio = $datos->get('idCriterio');
			$evaluacionCriterio->aprobado = $datos->get('aprobado');
			
            if ($evaluacionCriterio->save()) 
			{
				$borrado = DB::table('Hallazgo')					
				->where('idEvaluacionCalidadCalidadCriterio',$evaluacionCriterio->id)
				->update(['borradoAL' => NULL]);
			
				$hallazgo = Hallazgo::where('idEvaluacionCalidadCalidadCriterio',$evaluacionCriterio->id)->first();
				
				if(!$hallazgo)
					$hallazgo = new Hallazgo;				
				
				if($datos->get('aprobado')==0)
				{
					if($datos->get('accionx'))
					{
						$hallazgo->idUsuario = $usuario->id;
						$hallazgo->idAccion = $datos->get('accionx');
						$hallazgo->idEvaluacionCalidadCalidadCriterio = $evaluacionCriterio->id;
						$hallazgo->idPlazoAccion = $datos->get('plazoAccionx');
						$hallazgo->resuelto = $datos->get('resueltox');
						$hallazgo->descripcion = $datos->get('hallazgox');
						$hallazgo->cuantitativo = $datos->get('cuantitativox');
						$hallazgo->cantidad = $datos->get('cantidadx');
											
						$accion = Accion::find($datos->get('accionx'));
						
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
		$evaluacionCriterio = DB::table('EvaluacionCalidadCriterio AS e')
			->leftJoin('Clues AS c', 'c.clues', '=', 'e.clues')
			->leftJoin('ConeClues AS cc', 'cc.clues', '=', 'e.clues')
			->leftJoin('Cone AS co', 'co.id', '=', 'cc.idCone')
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
	
	
	/**
	 * Display a listing of the resource.
	 *
	 * @return Response
	 */
	public function CriterioEvaluacion($cone,$indicador)
	{		
		$datos = Request::all();
		
		$evaluacion = $datos['evaluacion'];
		$criterios = array();
		$criterio = DB::select("SELECT c.id as idCriterio, ic.idIndicador, cic.idCone, lv.id as idlugarVerificacion, c.creadoAl, c.modificadoAl, c.nombre as criterio, lv.nombre as lugarVerificacion FROM ConeIndicadorCriterio cic							
		left join IndicadorCriterio ic on ic.id = cic.idIndicadorCriterio
		left join Criterio c on c.id = ic.idCriterio
		left join LugarVerificacion lv on lv.id = ic.idlugarVerificacion		
		WHERE cic.idCone = $cone and ic.idIndicador = $indicador");	
		$totalCriterio = count($criterio);
		$CalidadRegistro = EvaluacionCalidadRegistro::where('idEvaluacionCalidad',$evaluacion)->get();
		foreach($CalidadRegistro as $registro)
		{
			$evaluacionCriterio = EvaluacionCalidadCriterio::where('idEvaluacionCalidad',$evaluacion)->where('idEvaluacionCalidadRegistro',$registro->id)->get();
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
			$criterio["registro"] = $registro;
			$criterios[$registro->columna]=$criterio;
		}
		
		if(!$criterios)
		{
			return Response::json(array('status'=> 404,"messages"=>'No encontrado'),404);
		} 
		else 
		{
			return Response::json(array("status"=>200,"messages"=>"ok","data"=>$criterios,"total"=>count($criterios),"totalCriterio"=>$totalCriterio),200);
			
		}
	}
	
	/**
	 * Display a listing of the resource.
	 *
	 * @return Response
	 */
	public function CriterioEvaluacionVer($evaluacion)
	{
		$totalPorcientoGeneralTemp = 0;
		$totalPorcientoGeneral = 0;
		$totalCriterios = 0;
		$totalAprobados = 0;
		$maximo = 0;
		$evaluacionC = DB::table('evaluacion AS e')
			->leftJoin('clues AS c', 'c.clues', '=', 'e.clues')
			->leftJoin('ConeClues AS cc', 'cc.clues', '=', 'e.clues')
			->leftJoin('cone AS co', 'co.id', '=', 'cc.idCone')
            ->select(array('e.fechaEvaluacion', 'e.cerrado', 'e.id','e.clues', 'c.nombre', 'c.domicilio', 'c.codigoPostal', 'c.entidad', 'c.municipio', 'c.localidad', 'c.jurisdiccion', 'c.institucion', 'c.tipoUnidad', 'c.estatus', 'c.estado', 'c.tipologia','co.nombre as nivelCone', 'cc.idCone'))
            ->where('e.id',"$evaluacion")
			->first();
		$criterios = array();
		$CalidadRegistro = EvaluacionCalidadRegistro::where('idEvaluacionCalidad',$evaluacion)->get();
		foreach($CalidadRegistro as $registro)
		{
			$evaluacionCriterio = EvaluacionCalidadCriterio::with('Evaluaciones')->where('idEvaluacionCalidadRegistro',$registro->id)->where('idEvaluacionCalidad',$evaluacion)->get();
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
					
					$in=[];
					foreach($total as $c)
					{
						$in[]=$c->id;
					}
					
					$aprobado = DB::table('EvaluacionCalidadCriterio')->select('idCriterio')->whereIN('idCriterio',$in)->where('idEvaluacionCalidad',$evaluacion)->where('aprobado',1)->where('idEvaluacionCalidadRegistro',$registro->id)->get();				
					$na = DB::table('EvaluacionCalidadCriterio')->select('idCriterio')->whereIN('idCriterio',$in)->where('idEvaluacionCalidad',$evaluacion)->where('aprobado',2)->where('idEvaluacionCalidadRegistro',$registro->id)->get();				
					
					$totalPorciento = number_format((count($aprobado)/(count($total)-count($na)))*100, 2, '.', '');
					
					$item->indicadores["totalCriterios"] = count($total)-count($na);
					$item->indicadores["totalAprobados"] = count($aprobado);
					$item->indicadores["totalPorciento"] = $totalPorciento;
					$color=DB::select("SELECT a.color FROM IndicadorAlerta ia 
									   left join Alerta a on a.id=ia.idAlerta
									   where ia.idIndicador = $id  and $totalPorciento between ia.minimo and ia.maximo");
										
					if($color)
						$color=$color[0]->color;
					else
						$color="gray";
					$item->indicadores["totalColor"] = $color;
					$indicadores[$item->codigo] = $item;
					
					$totalPorcientoGeneralTemp = $totalPorcientoGeneralTemp + $totalPorciento;
					$maximo++;
					$totalCriterios = $totalCriterios + count($total)-count($na);
					$totalAprobados = $totalAprobados +  count($aprobado);
					$totalPorcientoGeneral = $totalPorcientoGeneralTemp/$maximo;					
										
					$colorGeneral = DB::select("SELECT a.color FROM IndicadorAlerta ia 
											   left join Alerta a on a.id=ia.idAlerta
											   where ia.idIndicador = $id  and $totalPorcientoGeneral between ia.minimo and ia.maximo");
																   
					if($colorGeneral)
						$colorGeneral=$colorGeneral[0]->color;
					else
						$colorGeneral="gray";	

					$indicadores[$item->codigo]->totalCriterios = $totalCriterios;
					$indicadores[$item->codigo]->totalAprobados = $totalAprobados; 
					$indicadores[$item->codigo]->totalPorciento = $totalPorcientoGeneral;
					$indicadores[$item->codigo]->totalColor = $colorGeneral;
				}				
			}
			$criterio["noAplica"] = $noAplica;
			$criterio["aprobado"] = $aprobado;
			$criterio["noAprobado"] = $noAprobado;
			$criterio["hallazgo"] = $hallazgo;	
			$criterio["indicadores"] = $indicadores;			
			$criterio["expediente"] = $registro->expediente;
			
			$criterios[$registro->columna] = $criterio;
		}
		if(!$criterios)
		{
			return Response::json(array('status'=> 200, "messages"=> 'ok', "data"=> []),200);
		} 
		else 
		{
			return Response::json(array("status"=> 200, "messages"=> "ok", "data"=> $criterios, "total"=> count($criterios)),200);			
		}
	}
	
	/**
	 * Display a listing of the resource.
	 *
	 * @return Response
	 */
	public function Estadistica($evaluacion)
	{		
		$clues = EvaluacionCalidad::find($evaluacion)->first()->clues;
		
		$CalidadRegistro = EvaluacionCalidadRegistro::where('idEvaluacionCalidad',$evaluacion)->get();
		$columna=[];
		foreach($CalidadRegistro as $registro)
		{
			$evaluacionCriterio = EvaluacionCalidadCriterio::with('Evaluaciones')->where('idEvaluacionCalidadRegistro',$registro->id)->where('idEvaluacionCalidad',$evaluacion)->get(array('idCriterio','aprobado','id','idIndicador'));			
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
			$columna[$registro->columna] = $indicador;			
		}
		if(!$columna)
		{
			return Response::json(array('status'=> 200,"messages"=>'ok', "data"=> []),200);
		} 
		else 
		{
			return Response::json(array("status"=>200,"messages"=>"ok","data"=>$columna),200);			
		}
	}
}
?>