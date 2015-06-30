<?php namespace App\Http\Controllers\v1\Transacciones;

use App\Http\Requests;
use App\Http\Controllers\Controller;

use Response;
use Input;
use DB;
use Sentry;
use Request;

use App\Models\Transacciones\EvaluacionCalidadRegistro;
use App\Models\Catalogos\Clues;

class DashboardController extends Controller 
{	
    /**
	 * Display a listing of the resource.
	 *
	 * @return Response
	 */
	public function indicadorAbasto()
	{
		$datos = Request::all();
		$campo = $datos["campo"];
		$valor = $datos["valor"];
		$nivel = $datos["nivel"];
		
		$indicadores=$reporte = DB::select('select distinct codigo,indicador from Abasto');
		$cols=[];$serie=[];
		foreach($indicadores as $item)
		{
			array_push($serie,$item->indicador);
		}
		
		$nivelD = DB::select("select distinct $nivel from Abasto where 1 $valor order by anio,mes");
		$nivelDesglose=[];
		$color="hsla(0, 90%, 38%, 0.62)";
		
		for($x=0;$x<count($nivelD);$x++)
		{
			$a=$nivelD[$x]->$nivel;
			array_push($nivelDesglose,$a);
		}
				
		for($i=0;$i<count($serie);$i++)
		{
			$datos[$i] = [];
			$c=0;$porcentaje=0; $temp="";
			for($x=0;$x<count($nivelD);$x++)
			{
				$a=$nivelD[$x]->$nivel;
				$data["datasets"][$i]["label"]=$serie[$i];
				$sql="select Abasto.id,indicador,total,(((aprobado+noAplica)/total)*100) as porcentaje, 
				a.color, fechaEvaluacion,dia,mes,anio,day,month,semana,clues,Abasto.nombre,cone from Abasto 
				left join Alerta a on a.id=(select idAlerta from IndicadorAlerta where idIndicador=Abasto.id and 
				(((aprobado+noAplica)/total)*100) between minimo and maximo ) where $nivel = '$a' $valor and indicador = '$serie[$i]'";
				
				$reporte = DB::select($sql);
					
				if($temp!=$a)
				{
					$c=0;$porcentaje=0;
				}
				$indicador=0;
				if($reporte)
				{
					foreach($reporte as $r)
					{
						$porcentaje=$porcentaje+$r->porcentaje;
						$indicador=$r->id;
						$c++;
					}
					$temp = $a;
					$color=DB::select("select a.color from IndicadorAlerta ia left join Alerta a on a.id=ia.idAlerta where idIndicador=$indicador and 
				($porcentaje/$c) between minimo and maximo")[0]->color;
					array_push($datos[$i],$porcentaje/$c);													
				}
				else array_push($datos[$i],0);
				$highlightFill=explode(",",$color);
				$data["datasets"][$i]["fillColor"]=$color;
				$data["datasets"][$i]["strokeColor"]=$color;
				$data["datasets"][$i]["highlightFill"]=$highlightFill[0].",".$highlightFill[1].",".$highlightFill[2].",0.30)";
				$data["datasets"][$i]["highlightStroke"]=$color;
				$data["datasets"][$i]["data"]=$datos[$i];
			}
		}
		$data["labels"]=$nivelDesglose;
		if(!$data)
		{
			return Response::json(array('status' => 404, "messages" => 'No encontrado'),404);
		} 
		else 
		{
			return Response::json(array("status" => 200, "messages" => "ok", 
			"data" => $data, 
			"total" => count($reporte)),200);
			
		}
	}
	
	/**
	 * Display a listing of the resource.
	 *
	 * @return Response
	 */
	public function indicadorAbastoDimension()
	{
		$datos = Request::all();
		$campo = $datos["campo"];
		$valor = $datos["valor"];
		$nivel = $datos["nivel"];
		
		$nivelD = DB::select("select distinct $nivel from Abasto where 1 $valor");
		if($nivel=="clues")
		{
			$in=[];
			foreach($nivelD as $i)
				$in[]=$i->clues;
				
			$nivelD = Clues::whereIn("clues",$in)->get();
		}
		if(!$nivelD)
		{
			return Response::json(array('status' => 404, "messages" => 'No encontrado'),404);
		} 
		else 
		{
			return Response::json(array("status" => 200, "messages" => "ok", 
			"data" => $nivelD, 
			"total" => count($nivelD)),200);
		}
	}
	
	/**
	 * Display a listing of the resource.
	 *
	 * @return Response
	 */
	public function indicadorAbastoClues()
	{
		$datos = Request::all();
		$anio = $datos["anio"];
		$mes = $datos["mes"];
		$clues = $datos["clues"];
		
		$indicadores=$reporte = DB::select("select distinct codigo,indicador from Abasto where anio='$anio' and month='$mes' and clues='$clues' order by indicador");
		$cols=[];$serie=[];
		foreach($indicadores as $item)
		{
			array_push($serie,$item->indicador);
		}
		
		$nivelD = DB::select("select distinct evaluacion from Abasto where anio='$anio' and month='$mes' and clues='$clues'");
		$nivelDesglose=[];
		$color="hsla(0, 90%, 38%, 0.62)";
		
		for($x=0;$x<count($nivelD);$x++)
		{
			$a=$nivelD[$x]->evaluacion;
			array_push($nivelDesglose,"Evaluación #".$a);
		}
				
		for($i=0;$i<count($serie);$i++)
		{
			$datos[$i] = [];
			$c=0;$porcentaje=0; $temp="";
			for($x=0;$x<count($nivelD);$x++)
			{
				$a=$nivelD[$x]->evaluacion;
				$data["datasets"][$i]["label"]=$serie[$i];
				$sql="select Abasto.id,indicador,total,(((aprobado+noAplica)/total)*100) as porcentaje, 
				a.color, fechaEvaluacion,dia,mes,anio,day,month,semana,clues,Abasto.nombre,cone from Abasto 
				left join Alerta a on a.id=(select idAlerta from IndicadorAlerta where idIndicador=Abasto.id and 
				(((aprobado+noAplica)/total)*100) between minimo and maximo ) where anio='$anio' and month='$mes' and clues='$clues' and indicador = '$serie[$i]'";
				
				$reporte = DB::select($sql);
					
				if($temp!=$a) //if($temp!=$serie[$i])
				{
					$c=0;$porcentaje=0;
				}
				$indicador=0;
				if($reporte)
				{
					foreach($reporte as $r)
					{
						$porcentaje=$porcentaje+$r->porcentaje;
						$indicador=$r->id;
						$c++;
					}
					$temp = $a;
					$color=DB::select("select a.color from IndicadorAlerta ia left join Alerta a on a.id=ia.idAlerta where idIndicador=$indicador and 
				($porcentaje/$c) between minimo and maximo")[0]->color;
					array_push($datos[$i],$porcentaje/$c);													
				}
				else array_push($datos[$i],0);
				$highlightFill=explode(",",$color);
				$data["datasets"][$i]["fillColor"]=$color;
				$data["datasets"][$i]["strokeColor"]=$color;
				$data["datasets"][$i]["highlightFill"]=$highlightFill[0].",".$highlightFill[1].",".$highlightFill[2].",0.30)";
				$data["datasets"][$i]["highlightStroke"]=$color;
				$data["datasets"][$i]["data"]=$datos[$i];
			}
		}
		$data["labels"]=$nivelDesglose;
		if(!$data)
		{
			return Response::json(array('status' => 404, "messages" => 'No encontrado'),404);
		} 
		else 
		{
			return Response::json(array("status" => 200, "messages" => "ok", 
			"data" => $data, 
			"total" => count($reporte)),200);
			
		}
	}
	
	
	
	
	 /**
	 * Display a listing of the resource.
	 *
	 * @return Response
	 */
	public function indicadorCalidad()
	{
		$datos = Request::all();
		$campo = $datos["campo"];
		$valor = $datos["valor"];
		$nivel = $datos["nivel"];
		
		$indicadores=$reporte = DB::select('select distinct codigo,indicador from Calidad');
		$cols=[];$serie=[];
		foreach($indicadores as $item)
		{
			array_push($serie,$item->codigo);
		}
		
		$nivelD = DB::select("select distinct $nivel from Calidad where 1 $valor order by anio,mes");
		$nivelDesglose=[];
		$color="hsla(0, 90%, 38%, 0.62)";
		
		for($x=0;$x<count($nivelD);$x++)
		{
			$a=$nivelD[$x]->$nivel;
			array_push($nivelDesglose,$a);
		}
				
		for($i=0;$i<count($serie);$i++)
		{
			$datos[$i] = [];
			$c=0;$porcentaje=0; $temp="";
			for($x=0;$x<count($nivelD);$x++)
			{
				$a=$nivelD[$x]->$nivel;
				$data["datasets"][$i]["label"]=$serie[$i];
				$sql="select Calidad.id,indicador,total,Calidad.promedio as porcentaje, 
				a.color, fechaEvaluacion,dia,mes,anio,day,month,semana,clues,Calidad.nombre,cone from Calidad 
				left join Alerta a on a.id=(select idAlerta from IndicadorAlerta where idIndicador=Calidad.id and 
				(Calidad.promedio) between minimo and maximo ) where $nivel = '$a' $valor and codigo = '$serie[$i]'";
				
				$reporte = DB::select($sql);
				
				if($temp!=$a)
				{
					$c=0;$porcentaje=0;
				}
				$indicador=0;
				if($reporte)
				{
					foreach($reporte as $r)
					{
						$porcentaje=$porcentaje+$r->porcentaje;
						$indicador=$r->id;
						$c++;
					}
					$temp = $a;
					$color=DB::select("select a.color from IndicadorAlerta ia left join Alerta a on a.id=ia.idAlerta where idIndicador=$indicador and 
				($porcentaje/$c) between minimo and maximo")[0]->color;
					array_push($datos[$i],$porcentaje/$c);													
				}
				else array_push($datos[$i],0);
				
				$highlightFill=explode(",",$color);
				$data["datasets"][$i]["fillColor"]=$highlightFill[0].",".$highlightFill[1].",".$highlightFill[2].",0.20)";
				$data["datasets"][$i]["strokeColor"]=$highlightFill[0].",".$highlightFill[1].",".$highlightFill[2].",1)";
				$data["datasets"][$i]["pointColor"]=$color;
				$data["datasets"][$i]["pointStrokeColor"]=$highlightFill[0].",".$highlightFill[1].",".$highlightFill[2].",0.30)";
				$data["datasets"][$i]["pointHighlightFill"]=$highlightFill[0].",".$highlightFill[1].",".$highlightFill[2].",0.50)";
				$data["datasets"][$i]["pointHighlightStroke"]=$color;
				$data["datasets"][$i]["data"]=$datos[$i];
			}
		}
		$data["labels"]=$nivelDesglose;
		if(!$data)
		{
			return Response::json(array('status' => 404, "messages" => 'No encontrado'),404);
		} 
		else 
		{
			return Response::json(array("status" => 200, "messages" => "ok", 
			"data" => $data, 
			"total" => count($reporte)),200);
			
		}
	}
	
	/**
	 * Display a listing of the resource.
	 *
	 * @return Response
	 */
	public function indicadorCalidadDimension()
	{
		$datos = Request::all();
		$campo = $datos["campo"];
		$valor = $datos["valor"];
		$nivel = $datos["nivel"];
		
		$nivelD = DB::select("select distinct $nivel from Calidad where 1 $valor");
		if($nivel=="clues")
		{
			$in=[];
			foreach($nivelD as $i)
				$in[]=$i->clues;
				
			$nivelD = Clues::whereIn("clues",$in)->get();
		}
		if(!$nivelD)
		{
			return Response::json(array('status' => 404, "messages" => 'No encontrado'),404);
		} 
		else 
		{
			return Response::json(array("status" => 200, "messages" => "ok", 
			"data" => $nivelD, 
			"total" => count($nivelD)),200);
		}
	}
	
	/**
	 * Display a listing of the resource.
	 *
	 * @return Response
	 */
	public function indicadorCalidadClues()
	{
		$datos = Request::all();
		$anio = $datos["anio"];
		$mes = $datos["mes"];
		$clues = $datos["clues"];
		
		$indicadores=$reporte = DB::select("select distinct codigo,indicador from Calidad where anio='$anio' and month='$mes' and clues='$clues' order by indicador");
		$cols=[];$serie=[];
		foreach($indicadores as $item)
		{
			array_push($serie,$item->codigo);
		}
		
		$nivelD = DB::select("select distinct evaluacion from Calidad where anio='$anio' and month='$mes' and clues='$clues'");
		$nivelDesglose=[];
		$color="hsla(0, 90%, 38%, 0.62)";
		
		for($x=0;$x<count($nivelD);$x++)
		{
			$a=$nivelD[$x]->evaluacion;
			array_push($nivelDesglose,"Evaluación #".$a);
		}
				
		for($i=0;$i<count($serie);$i++)
		{
			$datos[$i] = [];
			$c=0;$porcentaje=0; $temp="";
			for($x=0;$x<count($nivelD);$x++)
			{
				$a=$nivelD[$x]->evaluacion;
				$data["datasets"][$i]["label"]=$serie[$i];
				$sql="select Calidad.id,indicador,total,Calidad.promedio as porcentaje, 
				a.color, fechaEvaluacion,dia,mes,anio,day,month,semana,clues,Calidad.nombre,cone from Calidad 
				left join Alerta a on a.id=(select idAlerta from IndicadorAlerta where idIndicador=Calidad.id and 
				(Calidad.promedio) between minimo and maximo ) where anio='$anio' and month='$mes' and clues='$clues' and codigo = '$serie[$i]'";
				
				$reporte = DB::select($sql);
					
				if($temp!=$a)
				{
					$c=0;$porcentaje=0;
				}
				$indicador=0;
				if($reporte)
				{
					foreach($reporte as $r)
					{
						$porcentaje=$porcentaje+$r->porcentaje;
						$indicador=$r->id;
						$c++;
					}
					$temp = $a;
					$color=DB::select("select a.color from IndicadorAlerta ia left join Alerta a on a.id=ia.idAlerta where idIndicador=$indicador and 
				($porcentaje/$c) between minimo and maximo")[0]->color;
					array_push($datos[$i],$porcentaje/$c);													
				}
				else array_push($datos[$i],0);
				$highlightFill=explode(",",$color);
				$data["datasets"][$i]["fillColor"]=$highlightFill[0].",".$highlightFill[1].",".$highlightFill[2].",0.20)";
				$data["datasets"][$i]["strokeColor"]=$highlightFill[0].",".$highlightFill[1].",".$highlightFill[2].",1)";
				$data["datasets"][$i]["pointColor"]=$color;
				$data["datasets"][$i]["pointStrokeColor"]=$highlightFill[0].",".$highlightFill[1].",".$highlightFill[2].",0.30)";
				$data["datasets"][$i]["pointHighlightFill"]=$highlightFill[0].",".$highlightFill[1].",".$highlightFill[2].",0.50)";
				$data["datasets"][$i]["pointHighlightStroke"]=$color;
				$data["datasets"][$i]["data"]=$datos[$i];
			}
		}
		$data["labels"]=$nivelDesglose;
		if(!$data)
		{
			return Response::json(array('status' => 404, "messages" => 'No encontrado'),404);
		} 
		else 
		{
			return Response::json(array("status" => 200, "messages" => "ok", 
			"data" => $data, 
			"total" => count($reporte)),200);
			
		}
	}
}
?>