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
				$evaluacionCriterio = EvaluacionCalidadCriterio::where($columna, 'LIKE', '%'.$valor.'%')->skip($pagina-1)->take($datos['limite'])->get();
				$total=$evaluacionCriterio;
			}
			else
			{
				$evaluacionCriterio = EvaluacionCalidadCriterio::skip($pagina-1)->take($datos['limite'])->get();
				$total=EvaluacionCalidadCriterio::get();
			}
			
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
	public function CriterioEvaluacion($cone,$indicador,$evaluacion)
	{		
		$datos = Request::all();
		
		$criterios = array();
		$criterio = DB::select("SELECT c.id as idCriterio, ic.idIndicador, cic.idCone, lv.id as idlugarVerificacion, c.creadoAl, c.modificadoAl, c.nombre as criterio, lv.nombre as lugarVerificacion FROM ConeIndicadorCriterio cic							
		left join IndicadorCriterio ic on ic.id = cic.idIndicadorCriterio
		left join Criterio c on c.id = ic.idCriterio
		left join LugarVerificacion lv on lv.id = ic.idlugarVerificacion		
		WHERE cic.idCone = $cone and ic.idIndicador = $indicador");
		$totalCriterio = count($criterio);
		$CalidadRegistro = EvaluacionCalidadRegistro::where('idEvaluacionCalidad',$evaluacion)->where('idIndicador',$indicador)->get();	
			
		if(!$CalidadRegistro->toArray())
		{
			$criterios[1]=$criterio;
			$criterios[1]["registro"]["columna"]=1;
		}
		if($criterio)
		foreach($CalidadRegistro as $registro)
		{
			$evaluacionCriterio = EvaluacionCalidadCriterio::where('idEvaluacionCalidad',$evaluacion)->where('idIndicador',$indicador)->where('idEvaluacionCalidadRegistro',$registro->id)->get();
			
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
						
			$criterio["registro"] = $registro;
			$criterios[$registro->columna]=$criterio;
		}
		
		if(!$criterios||!$criterio)
		{
			return Response::json(array('status'=> 404,"messages"=>'No se encontro criterios'),404);
		} 
		else 
		{
			$result = DB::select("SELECT h.idIndicador, h.idAccion, h.idPlazoAccion, h.resuelto, h.descripcion, a.tipo 
			FROM Hallazgo h	
			left join Accion a on a.id = h.idAccion WHERE h.idEvaluacion = $evaluacion and categoriaEvaluacion='CALIDAD'");
				
			if($result)
			{
				foreach($result as $r)
				{
					$hallazgo[$r->idIndicador] = $r;
				}
			}
			else $hallazgo=0;
			return Response::json(array("status"=>200,"messages"=>"ok","data"=>$criterios,"total"=>count($criterios),"totalCriterio"=>$totalCriterio,"hallazgo" => $hallazgo),200);
			
		}
	}
	
	/**
	 * Display a listing of the resource.
	 *
	 * @return Response
	 */
	public function CriterioEvaluacionVer($evaluacion)
	{
		$data=[];
			
		$sql="select distinct id,codigo,indicador,cone,idCone from Calidad where evaluacion='$evaluacion' order by codigo";		
		$indicadores = DB::select($sql);
		
		foreach($indicadores as $indicador)
		{
			$criteriosx = DB::select("SELECT c.id,c.nombre, l.nombre as lugar FROM IndicadorCriterio ic 
				left join ConeIndicadorCriterio cic on cic.idCone = '$indicador->idCone'
				left join Criterio c on c.id = ic.idCriterio
				left join LugarVerificacion l on l.id = ic.idLugarVerificacion
				where ic.idIndicador = '$indicador->id' and ic.id=cic.idIndicadorCriterio");	
			$data["criterios"][$indicador->codigo]=$criteriosx;
			$data["indicadores"][$indicador->codigo] = $indicador;
			
			$sql="select id, idIndicador, columna, expediente, cumple, promedio, totalCriterio 
				  from EvaluacionCalidadRegistro 
				  where idEvaluacionCalidad='$evaluacion' and idIndicador='$indicador->id' and borradoAl is null";	
			
			$registros = DB::select($sql);
			$bien=0;$suma=0;
			foreach($registros as $registro)
			{
				$aprobado=array();
				$noAplica=array();
				$noAprobado=array();
				$sql="select ecc.id, ecc.aprobado, ecc.idCriterio, c.nombre 
				  from EvaluacionCalidadCriterio  ecc
				  left join Criterio c on c.id = ecc.idCriterio
				  where ecc.idEvaluacionCalidadRegistro='$registro->id' 
				  and ecc.idEvaluacionCalidad='$evaluacion' 
				  and ecc.idIndicador='$indicador->id' 
				  and ecc.borradoAl is null";	
			
				$criterios = DB::select($sql);
				foreach($criterios as $criterio)
				{
					if($criterio->aprobado == '1')
					{
						array_push($aprobado,$criterio->idCriterio);
						$bien++;
					}
					else if($criterio->aprobado == '2')
					{
						array_push($noAplica,$criterio->idCriterio);
						$bien++;
					}
					else
					{
						array_push($noAprobado,$criterio->idCriterio);								
					}	
				}
				$data["datos"][$indicador->codigo][$registro->columna] = $criterios;
				
				$data["indicadores"][$indicador->codigo]->columnas[$registro->columna]["total"]=count($aprobado)+count($noAplica);
				$data["indicadores"][$indicador->codigo]->columnas[$registro->columna]["expediente"]=$registro->expediente;
				$suma+=count($aprobado)+count($noAplica);
				$totalPorciento = number_format(((count($aprobado)+count($noAplica))/(count($criteriosx)))*100, 2, '.', '');
				$color=DB::select("SELECT a.color FROM IndicadorAlerta ia 
									   left join Alerta a on a.id=ia.idAlerta
									   where ia.idIndicador = $indicador->id  and $totalPorciento between ia.minimo and ia.maximo");
										
				if($color)
					$color=$color[0]->color;
				else $color="hsla(0, 2%, 37%, 0.62)";
				$data["indicadores"][$indicador->codigo]->columnas[$registro->columna]["color"]=$color;
			}
			$data["indicadores"][$indicador->codigo]->totalCriterio=count($criteriosx)*$registro->columna;
			$data["indicadores"][$indicador->codigo]->totalColumnas=$registro->columna;
			$data["indicadores"][$indicador->codigo]->sumaCriterio=$suma;
			
			$totalPorciento = number_format(($suma/($data["indicadores"][$indicador->codigo]->totalCriterio))*100, 2, '.', '');
			$color=DB::select("SELECT a.color FROM IndicadorAlerta ia 
									   left join Alerta a on a.id=ia.idAlerta
									   where ia.idIndicador = $indicador->id  and $totalPorciento between ia.minimo and ia.maximo");
					
				if($color)
					$color=$color[0]->color;
			$data["indicadores"][$indicador->codigo]->porciento=$totalPorciento;	
			$data["indicadores"][$indicador->codigo]->color=$color;
		}
		
		if(!$data)
		{
			return Response::json(array('status'=> 200, "messages"=> 'ok', "data"=> []),200);
		} 
		else 
		{
			return Response::json(array("status"=> 200, "messages"=> "ok", "data"=> $data, "total"=> count($indicadores)),200);			
		}
	}
	
	/**
	 * Display a listing of the resource.
	 *
	 * @return Response
	 */
	public function Estadistica($evaluacion,$indicador)
	{		
		$clues = EvaluacionCalidad::find($evaluacion)->first()->clues;
		
		$CalidadRegistro = EvaluacionCalidadRegistro::where('idEvaluacionCalidad',$evaluacion)->where('idIndicador',$indicador)->get();
		$columna=[]; $col=0;
		
		foreach($CalidadRegistro as $registro)
		{
			$evaluacionCriterio = EvaluacionCalidadCriterio::where('idIndicador',$indicador)
								->where('idEvaluacionCalidadRegistro',$registro->id)
								->where('idEvaluacionCalidad',$evaluacion)
								->get(array('idCriterio','aprobado','id','idIndicador'));			
			$indicadores = [];
			
			foreach($evaluacionCriterio as $item)
			{
				$sql = "SELECT distinct i.id, i.codigo, i.nombre, 
				(SELECT count(id) FROM ConeIndicadorCriterio where idIndicadorCriterio in(select id from IndicadorCriterio where  idIndicador=ci.idIndicador) and idCone=cc.idCone) as total 
				FROM ConeClues cc 
				left join ConeIndicadorCriterio cic on cic.idCone = cc.idCone
				left join IndicadorCriterio ci on ci.id = cic.idIndicadorCriterio 
				left join Indicador i on i.id = ci.idIndicador
				where cc.clues = '$clues' and ci.idCriterio = $item->idCriterio and ci.idIndicador = $registro->idIndicador and i.id is not null";
				
				$result = DB::select($sql);
				
				if($result)
				{
					$result = (array)$result[0];
					$existe = false; $contador=0;
					for($i=0;$i<count($indicadores);$i++)
					{
						if(array_key_exists($result["codigo"],$indicadores[$i]))
						{						
							$indicadores[$i][$result["codigo"]]=$indicadores[$i][$result["codigo"]]+1;						
							$existe = true;
						}
					}
				}
				if(!$existe)
				{
					$contador=1;
					
					$result[$result["codigo"]] = $contador;
					array_push($indicadores,$result);
				}
			}
			
			$columna[$registro->columna] = $indicadores;			
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