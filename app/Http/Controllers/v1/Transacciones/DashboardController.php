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
		
		$indicadores = DB::select('select distinct codigo,indicador from Abasto');
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
					$porcentaje = number_format($porcentaje/$c, 2, '.', ',');
					$color=DB::select("select a.color from IndicadorAlerta ia left join Alerta a on a.id=ia.idAlerta where idIndicador=$indicador and 
				($porcentaje) between minimo and maximo")[0]->color;
					array_push($datos[$i],$porcentaje);													
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
			"total" => count($data)),200);
			
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
		
		$indicadores = DB::select("select distinct codigo,indicador from Abasto where anio='$anio' and month='$mes' and clues='$clues' order by indicador");
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
					$porcentaje = number_format($porcentaje/$c, 2, '.', ',');
					$color=DB::select("select a.color from IndicadorAlerta ia left join Alerta a on a.id=ia.idAlerta where idIndicador=$indicador and 
				($porcentaje) between minimo and maximo")[0]->color;
					array_push($datos[$i],$porcentaje);													
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
			"total" => count($data)),200);
			
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
		
		$indicadores = DB::select('select distinct codigo,indicador from Calidad');
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
					$porcentaje = number_format($porcentaje/$c, 2, '.', ',');
					$color=DB::select("select a.color from IndicadorAlerta ia left join Alerta a on a.id=ia.idAlerta where idIndicador=$indicador and 
				($porcentaje) between minimo and maximo")[0]->color;
					array_push($datos[$i],$porcentaje);													
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
			"total" => count($data)),200);
			
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
		
		$indicadores = DB::select("select distinct codigo,indicador from Calidad where anio='$anio' and month='$mes' and clues='$clues' order by indicador");
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
					$porcentaje = number_format($porcentaje/$c, 2, '.', ',');
					$color=DB::select("select a.color from IndicadorAlerta ia left join Alerta a on a.id=ia.idAlerta where idIndicador=$indicador and 
				($porcentaje) between minimo and maximo")[0]->color;
					array_push($datos[$i],$porcentaje);													
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
			"total" => count($data)),200);
			
		}
	}
	
	/**
	 * Display a listing of the resource.
	 *
	 * @return Response
	 */
	public function alerta()
	{
		$datos = Request::all();
		$anio = $datos["anio"];
		$mes = $datos["mes"];
		$clues = $datos["clues"];
		$tipo = $datos["tipo"];
		
		$sql="select distinct codigo,indicador from $tipo where anio='$anio'";
		if($mes!="")
			$sql.=" and month='$mes'";
		if($clues!="")
			$sql.=" and clues='$clues'";
		$sql.="order by indicador";
		$indicadores = DB::select($sql);
		$serie=[]; $codigo=[];
		foreach($indicadores as $item)
		{
			array_push($serie,$item->indicador);
			array_push($codigo,$item->codigo);
		}
		$data=[]; $temp="";
		for($i=0;$i<count($serie);$i++)
		{
			if($tipo=="Abasto")
			{
				$sql="select Abasto.id,indicador,total,(((aprobado+noAplica)/total)*100) as porcentaje, 
					a.color, fechaEvaluacion,dia,mes,anio,day,month,semana,clues,Abasto.nombre,cone from Abasto 
					left join Alerta a on a.id=(select idAlerta from IndicadorAlerta where idIndicador=Abasto.id and 
					(((aprobado+noAplica)/total)*100) between minimo and maximo ) where anio='$anio' and indicador = '$serie[$i]'";
			}
			
			if($tipo=="Calidad")
			{
				$sql="select Calidad.id,indicador,total,Calidad.promedio as porcentaje, 
					a.color, fechaEvaluacion,dia,mes,anio,day,month,semana,clues,Calidad.nombre,cone from Calidad 
					left join Alerta a on a.id=(select idAlerta from IndicadorAlerta where idIndicador=Calidad.id and 
					(Calidad.promedio) between minimo and maximo ) where anio='$anio' and indicador = '$serie[$i]'";
			}
			if($mes!="")
				$sql.=" and month='$mes'";
			if($clues!="")
				$sql.=" and clues='$clues'";
			$reporte = DB::select($sql);
			
			$indicador=0;
			if($reporte)
			{
				foreach($reporte as $r)
				{
					$a=$serie[$i];
					if($temp!=$a)
					{
						$c=0;$porcentaje=0;
					}
					$porcentaje=$porcentaje+$r->porcentaje;
					$indicador=$r->id;
					$c++;
					$temp = $a;
				}
				$porcentaje = number_format($porcentaje/$c, 2, '.', ',');
				$color=DB::select("select a.color from IndicadorAlerta ia left join Alerta a on a.id=ia.idAlerta where idIndicador=$indicador and 
			($porcentaje) between minimo and maximo")[0]->color;
				 array_push($data,array("codigo" => $codigo[$i],"nombre" => $serie[$i],"color" => $color, "porcentaje" => $porcentaje));													
			}
			else array_push($data,array("codigo" => $codigo[$i],"nombre" => $serie[$i],"color" => "#357ebd", "porcentaje" => "N/A"));
		}
		if(!$data)
		{
			return Response::json(array('status' => 404, "messages" => 'No encontrado'),404);
		} 
		else 
		{
			return Response::json(array("status" => 200, "messages" => "ok", 
			"data" => $data, 
			"total" => count($data)),200);
		}
	}
	
	/**
	 * Display a listing of the resource.
	 *
	 * @return Response
	 */
	public function hallazgoGauge()
	{
		$datos = Request::all();
		$anio = $datos["anio"];
		$mes = $datos["mes"];
		$clues = $datos["clues"];
		$tipo = strtoupper($datos["tipo"]);
		
		$sql="SELECT count(sh.id) as total, (select count(id) from SeguimientoHallazgo where resuelto=1 and categoria='$tipo') as resuelto FROM SeguimientoHallazgo sh";
		if($tipo=="ABASTO")
		{
			$sql.=" LEFT JOIN Evaluacion e on e.id = sh.idEvaluacion";
		}
		if($tipo=="CALIDAD")
		{
			$sql.=" LEFT JOIN EvaluacionCalidad e on e.id = sh.idEvaluacion";
		}
		$sql.=" LEFT JOIN Clues c on c.clues = e.clues
				LEFT JOIN ConeClues cc on cc.clues = c.clues
				LEFT JOIN Cone cn on cn.id = cc.idCone
				where  e.borradoAl is null and sh.categoria='$tipo'";
		
		if($anio!="")
			$sql.=" and sh.anio='$anio'";
		if($mes!="")
			$sql.=" and sh.month='$mes'";
		if($clues!="")
			$sql.=" and c.clues='$clues'";
				
		$data = DB::select($sql);
		
		if(!$data)
		{
			return Response::json(array('status' => 404, "messages" => 'No encontrado'),404);
		} 
		else 
		{			
			$resuelto = $data[0]->resuelto;
			$total = $data[0]->total;
			
			$rojo = ($total*.5);
			$nara = ($total*.6);
			$amar = ($total*.8);
			$verd = $total;
			
			$rangos[0] = array('min' => 0,     'max' => $rojo, 'color' => '#C50200');
			$rangos[1] = array('min' => $rojo, 'max' => $nara, 'color' => '#FF7700');
			$rangos[2] = array('min' => $nara, 'max' => $amar, 'color' => '#FDC702');
			$rangos[3] = array('min' => $amar, 'max' => $verd, 'color' => '#8DCA2F');
			
			$sql="SELECT sh.codigo, sh.indicador, c.nombre as um,sh.accion, sh.descripcion FROM SeguimientoHallazgo sh";
			if($tipo=="ABASTO")
			{
				$sql.=" LEFT JOIN Evaluacion e on e.id = sh.idEvaluacion";
			}
			if($tipo=="CALIDAD")
			{
				$sql.=" LEFT JOIN EvaluacionCalidad e on e.id = sh.idEvaluacion";
			}
			$sql.=" LEFT JOIN Clues c on c.clues = e.clues
					LEFT JOIN ConeClues cc on cc.clues = c.clues
					LEFT JOIN Cone cn on cn.id = cc.idCone
					where  e.borradoAl is null and sh.categoria='$tipo'";
			
			if($anio!="")
				$sql.=" and sh.anio='$anio'";
			if($mes!="")
				$sql.=" and sh.month='$mes'";
			if($clues!="")
				$sql.=" and c.clues='$clues'";
				
			$data = DB::select($sql);
		
			return Response::json(array("status" => 200, "messages" => "ok", 
			"data"  => $data,
			"valor" => $resuelto,
			"rangos"=> $rangos,
			"total" => $total),200);
		}
	}
	
	/**
	 * Display a listing of the resource.
	 *
	 * @return Response
	 */
	public function gaugeDimension()
	{
		$datos = Request::all();
		$campo = $datos["campo"];
		$valor = $datos["valor"];
		$nivel = $datos["nivel"];
		$tipo = strtoupper($datos["tipo"]);
		
		$sql="select distinct $nivel from SeguimientoHallazgo sh";
		if($tipo=="ABASTO")
		{
			$sql.=" LEFT JOIN Evaluacion e on e.id = sh.idEvaluacion";
		}
		if($tipo=="CALIDAD")
		{
			$sql.=" LEFT JOIN EvaluacionCalidad e on e.id = sh.idEvaluacion";
		}
		$sql.=" LEFT JOIN Clues c on c.clues = e.clues
				LEFT JOIN ConeClues cc on cc.clues = c.clues
				LEFT JOIN Cone cn on cn.id = cc.idCone
				where  e.borradoAl is null $valor and sh.categoria='$tipo'";
		
		$nivelD = DB::select($sql);
		if($nivel=="c.clues")
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
	public function indicadorCalidadGlobal()
	{
		$datos = Request::all();
		$anio = $datos["anio"];
		$mes = $datos["mes"];
		$sql="select distinct codigo,indicador from Calidad where 1";
		if($anio!="")
			$sql.=" and anio='$anio'";
		if($mes!="")
			$sql.=" and month='$mes'";
		$sql.=" order by codigo";
		
		$indicadores = DB::select($sql);
		
		$sql="select distinct clues,nombre from Calidad where 1";
		if($anio!="")
			$sql.=" and anio='$anio'";
		if($mes!="")
			$sql.=" and month='$mes'";
		
		$clues = DB::select($sql);
		$color="hsla(242, 90%, 49%, 0.62)";
		for($x=0;$x<count($clues);$x++)			
		{
			$data[$clues[$x]->nombre]=[];
			for($i=0;$i<count($indicadores);$i++)
			{
				$c=0;
				$um=$clues[$x]->clues;
				$codigo=$indicadores[$i]->codigo;
				$sql="select Calidad.id,indicador,total,Calidad.promedio as porcentaje, clues,Calidad.nombre,cone from Calidad 
				where clues='$um' and codigo = '$codigo'";
				
				if($anio!="")
					$sql.=" and anio='$anio'";
				if($mes!="")
					$sql.=" and month='$mes'";
		
				$reporte = DB::select($sql);
				$porcentaje=0;
				if($reporte)
				{
					foreach($reporte as $r)
					{
						$porcentaje=$porcentaje+$r->porcentaje;
						$indicador=$r->id;
						$c++;
					}
					$porcentaje = number_format($porcentaje/$c, 2, '.', ',');
					$color=DB::select("select a.color from IndicadorAlerta ia left join Alerta a on a.id=ia.idAlerta where idIndicador=$indicador and 
					($porcentaje) between minimo and maximo")[0]->color;
					array_push($data[$clues[$x]->nombre],array("indicador" => $indicadores[$i]->indicador, "codigo" => $indicadores[$i]->codigo, "color" => $color, "porcentaje" => $porcentaje));													
				}
				else 
					array_push($data[$clues[$x]->nombre],array("indicador" => $indicadores[$i]->indicador, "codigo" => $indicadores[$i]->codigo, "color" => "hsla(242, 90%, 49%, 0.62)", "porcentaje" => 0));																	
			}
		}
		if(!$data)
		{
			return Response::json(array('status' => 404, "messages" => 'No encontrado'),404);
		} 
		else 
		{
			return Response::json(array("status" => 200, "messages" => "ok", 
			"data" => $data, 
			"indicadores" => $indicadores,
			"total" => count($data)),200);
			
		}
	}
}
?>