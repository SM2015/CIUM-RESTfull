<?php
/**
 * Controlador Dashboard
 * 
 * @package    CIUM API
 * @subpackage Controlador
 * @author     Eliecer Ramirez Esquinca
 * @created    2015-07-20
 */
namespace App\Http\Controllers\v1\Transacciones;

use App\Http\Requests;
use App\Http\Controllers\Controller;

use Response;
use Input;
use DB;
use Session;
use Sentry;
use Request;

use App\Models\Transacciones\EvaluacionCalidadRegistro;
use App\Models\Catalogos\Clues;
use App\Models\Catalogos\ConeClues;

class DashboardController extends Controller 
{	
    /**
	 * Muestra una lista de los recurso para el gráfico Abasto en el dashboard.
	 *
	 * @param  $request
	 *			campo = campo de la base de datos donde recuperar el dato
	 *		    valor = valor de la variable nivel
	 *			nivel  = espacio tiempo
	 * Response si se puede crear json con los datos y estado 200 si no error y estado 404
	 * @return Response
	 */
	public function indicadorAbasto()
	{
		$datos = Request::all();
		$campo = $datos["campo"];
		$valor = $datos["valor"];
		$nivel = $datos["nivel"];
		// verificar que la variable de session que contiene las clues que puede ver el usuario este vacia
		if($nivel=="anio"||$nivel=="month"||$nivel=="jurisdiccion"||$nivel=="")
			Session::forget('cluesUsuario');
		// validar que exista la sesion para agregarle valor en caso contrario traer las clues del usuario
		if (Session::has('cluesUsuario'))
			$cluesUsuario=Session::get('cluesUsuario');
		else
			$cluesUsuario=$this->permisoZona();
		
		// si el nivel de  desglose es el nivel de clues, comprobar que pertenezca al usuario
		if($nivel=="clues")
		{			
			$parametro = $datos["parametro"];
			$nivelD = DB::select("select distinct zc.clues from ZonaClues zc 
								LEFT JOIN Zona z on z.id=zc.idZona 
								WHERE zc.clues in ($cluesUsuario) and z.nombre='$parametro' or z.id='$parametro'");
			$cluesUsuario=array();
			foreach($nivelD as $item)			
				array_push($cluesUsuario,"'".$item->clues."'");
			$cluesUsuario=implode(",",$cluesUsuario);
		}
		// si el nivel es por zona, traer todos las clues de la zona
		if($nivel=="zona")
		{
			$parametro = $datos["parametro"];
			$nivelD = DB::select("select distinct z.id,z.nombre, z.nombre as zona from ZonaClues zc 
								LEFT JOIN Zona z on z.id=zc.idZona 
								WHERE zc.clues in ($cluesUsuario) and zc.jurisdiccion='$parametro'");
			$zonas = array();
			$zonaClues = array();
			foreach($nivelD as $item)
			{			
				array_push($zonas,$item->id);
				$res = DB::select("select distinct clues from ZonaClues where idZona='$item->id'");
				$x=array();
				foreach($res as $i)			
					array_push($x,"'".$i->clues."'");
				$zonaClues["$item->nombre"] = implode(",",$x);
			}
			
			$zonas=implode(",",$zonas);
			$result = DB::select("select distinct clues from ZonaClues where idZona in ($zonas)");
			$cluesUsuario=array();
			foreach($result as $item)			
				array_push($cluesUsuario,"'".$item->clues."'");
			$cluesUsuario=implode(",",$cluesUsuario);
			
			Session::put('cluesUsuario', $cluesUsuario);
		}
		// todos los indicadores que tengan al menos una evaluación
		$indicadores = DB::select("select distinct color,codigo,indicador from Abasto where clues in ($cluesUsuario) $valor");
		$cols=[];$serie=[]; $colorInd=[];
		foreach($indicadores as $item)
		{
			array_push($serie,$item->indicador);
			array_push($colorInd,$item->color);
		}
		if($nivel!="zona")
			$nivelD = DB::select("select distinct $nivel from Abasto where clues in ($cluesUsuario) $valor order by anio,mes");	
		$nivelDesglose=[];
		$color="hsla(0, 90%, 38%, 0.62)";
		
		for($x=0;$x<count($nivelD);$x++)
		{
			$a=$nivelD[$x]->$nivel;
			array_push($nivelDesglose,$a);
		}
		// recorrer los indicadores para obtener sus valores con respecto al filtrado		
		for($i=0;$i<count($serie);$i++)
		{
			$datos[$i] = [];
			$c=0;$porcentaje=0; $temp="";
			for($x=0;$x<count($nivelD);$x++)
			{
				$a=$nivelD[$x]->$nivel;
				if($nivel=="zona"){$nivel=1; $a=1;}
				$data["datasets"][$i]["label"]=$serie[$i];
				$sql="select Abasto.id,indicador,total,(((aprobado+noAplica)/total)*100) as porcentaje, 
				a.color, fechaEvaluacion,dia,mes,anio,day,month,semana,clues,Abasto.nombre,cone from Abasto 
				left join Alerta a on a.id=(select idAlerta from IndicadorAlerta where idIndicador=Abasto.id and 
				(((aprobado+noAplica)/total)*100) between minimo and maximo ) where $nivel = '$a' $valor and indicador = '$serie[$i]'";
				if($nivel=="1" && $a=="1" )
				{
					$a=$nivelD[$x]->zona;
					$nivel="zona";
					$sql.=" and clues In (".$zonaClues["$a"].")";
				}
				$reporte = DB::select($sql);
				
				if($temp!=$a)
				{
					$c=0;$porcentaje=0;
				}
				$indicador=0;
				// conseguir el color de las alertas
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
				// array para el empaquetado de los datos y poder pintar con la libreria js-chart en angular
				$highlightFill=explode(",",$colorInd[$i]);
				$data["datasets"][$i]["fillColor"]=$colorInd[$i];
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
	 * Recupera las dimensiones para el filtrado del gráfico Abasto.
	 *
	 * @param  $request
	 *			campo = campo de la base de datos donde recuperar el dato
	 *		    valor = valor de la variable nivel
	 *			nivel  = espacio tiempo
	 * Response si se puede crear json con los datos y estado 200 si no error y estado 404
	 * @return Response
	 */
	public function indicadorAbastoDimension()
	{
		$datos = Request::all();
		$campo = $datos["campo"];
		$valor = $datos["valor"];
		$nivel = $datos["nivel"];
		
		if($nivel=="anio"||$nivel=="month"||$nivel=="jurisdiccion")
			Session::forget('cluesUsuario');
		if (Session::has('cluesUsuario'))
			$cluesUsuario=Session::get('cluesUsuario');
		else
			$cluesUsuario=$this->permisoZona();
		
		if($nivel=="zona")
		{
			$parametro = $datos["parametro"];
			$nivelD = DB::select("select distinct z.id,z.nombre from ZonaClues zc 
								LEFT JOIN Zona z on z.id=zc.idZona 
								WHERE zc.clues in ($cluesUsuario) and zc.jurisdiccion='$parametro'");
			$zonas = array();
			foreach($nivelD as $item)			
				array_push($zonas,$item->id);
			
			$zonas=implode(",",$zonas);
			$result = DB::select("select distinct clues from ZonaClues where idZona in ($zonas)");
			$cluesUsuario=array();
			foreach($result as $item)			
				array_push($cluesUsuario,"'".$item->clues."'");
			$cluesUsuario=implode(",",$cluesUsuario);
			
			Session::put('cluesUsuario', $cluesUsuario);
		}
		if($nivel!="zona")
			$nivelD = DB::select("select distinct $nivel from Abasto where clues in ($cluesUsuario) $valor");
		
		if($nivel=="month")
		{
			Session::forget('cluesUsuario');
			$nivelD=$this->getBimestre($nivelD);			
		}
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
	 * Muestra las evaluaciones que correspondan con el ultimo nivel de filtrado.
	 *
	 * @param  $request
	 *			anio 
	 *		    mes 
	 *			clues  
	 * Response si se puede crear json con los datos y estado 200 si no error y estado 404
	 * @return Response
	 */
	public function indicadorAbastoClues()
	{
		$datos = Request::all();
		$anio = isset($datos["anio"]) ? $datos["anio"] : '';
		$mes = isset($datos["mes"]) ? $datos["mes"] : '';
		$clues = isset($datos["clues"]) ? $datos["clues"] : '';
		
		$cluesUsuario=$this->permisoZona();
		$sql="select distinct codigo,indicador,color from Abasto where anio='$anio' and clues='$clues' and clues in ($cluesUsuario) ";
		if(!strpos($mes,"and"))
			$sql.="and month='$mes'";
		else
			$sql.="and mes between $mes ";
		$sql.="order by indicador";
		$indicadores = DB::select($sql);
		$cols=[];$serie=[]; $colorInd=[];
		foreach($indicadores as $item)
		{
			array_push($serie,$item->indicador);
			array_push($colorInd,$item->color);
		}
		$sql="select distinct evaluacion from Abasto where anio='$anio' and clues='$clues' and clues in ($cluesUsuario)";
		if(!strpos($mes,"and"))
			$sql.="and month='$mes'";
		else
			$sql.="and mes between $mes ";
		$nivelD = DB::select($sql);
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
				(((aprobado+noAplica)/total)*100) between minimo and maximo ) where anio='$anio' and clues='$clues' and indicador = '$serie[$i]'";
				
				if(!strpos($mes,"and"))
					$sql.="and month='$mes'";
				else
					$sql.="and mes between $mes ";
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
				
				$highlightFill=explode(",",$colorInd[$i]);
				$data["datasets"][$i]["fillColor"]=$colorInd[$i];
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
	 * Muestra una lista de los recurso para el gráfico Calidad en el dashboard.
	 *
	 * @param  $request
	 *			campo = campo de la base de datos donde recuperar el dato
	 *		    valor = valor de la variable nivel
	 *			nivel  = espacio tiempo
	 * Response si se puede crear json con los datos y estado 200 si no error y estado 404
	 * @return Response
	 */
	public function indicadorCalidad()
	{
		$datos = Request::all();
		$campo = $datos["campo"];
		$valor = $datos["valor"];
		$nivel = $datos["nivel"];
		
		if($nivel=="anio"||$nivel=="month"||$nivel=="jurisdiccion"||$nivel=="")
			Session::forget('cluesUsuario');
		if (Session::has('cluesUsuario'))
			$cluesUsuario=Session::get('cluesUsuario');
		else
			$cluesUsuario=$this->permisoZona();
		
		if($nivel=="clues")
		{			
			$parametro = $datos["parametro"];
			$nivelD = DB::select("select distinct zc.clues from ZonaClues zc 
								LEFT JOIN Zona z on z.id=zc.idZona 
								WHERE zc.clues in ($cluesUsuario) and z.nombre='$parametro' or z.id='$parametro'");
			$cluesUsuario=array();
			foreach($nivelD as $item)			
				array_push($cluesUsuario,"'".$item->clues."'");
			$cluesUsuario=implode(",",$cluesUsuario);
		}
		if($nivel=="zona")
		{
			$parametro = $datos["parametro"];
			$nivelD = DB::select("select distinct z.id,z.nombre, z.nombre as zona from ZonaClues zc 
								LEFT JOIN Zona z on z.id=zc.idZona 
								WHERE zc.clues in ($cluesUsuario) and zc.jurisdiccion='$parametro'");
			$zonas = array();
			$zonaClues = array();
			foreach($nivelD as $item)
			{			
				array_push($zonas,$item->id);
				$res = DB::select("select distinct clues from ZonaClues where idZona='$item->id'");
				$x=array();
				foreach($res as $i)			
					array_push($x,"'".$i->clues."'");
				$zonaClues["$item->nombre"] = implode(",",$x);
			}
			
			$zonas=implode(",",$zonas);
			$result = DB::select("select distinct clues from ZonaClues where idZona in ($zonas)");
			$cluesUsuario=array();
			foreach($result as $item)			
				array_push($cluesUsuario,"'".$item->clues."'");
			$cluesUsuario=implode(",",$cluesUsuario);
			
			Session::put('cluesUsuario', $cluesUsuario);
		}
		
		$indicadores = DB::select("select distinct color,codigo,indicador from Calidad where clues in ($cluesUsuario)");
		$cols=[];$serie=[]; $colorInd=[];
		foreach($indicadores as $item)
		{
			array_push($serie,$item->codigo);
			array_push($colorInd,$item->color);
		}
		if($nivel!="zona")
			$nivelD = DB::select("select distinct $nivel from Calidad where clues in ($cluesUsuario) $valor order by anio,mes");
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
				if($nivel=="zona"){$nivel=1; $a=1;}
				$data["datasets"][$i]["label"]=$serie[$i];
				$sql="select Calidad.id,indicador,total,Calidad.promedio as porcentaje, 
				a.color, fechaEvaluacion,dia,mes,anio,day,month,semana,clues,Calidad.nombre,cone from Calidad 
				left join Alerta a on a.id=(select idAlerta from IndicadorAlerta where idIndicador=Calidad.id and 
				(Calidad.promedio) between minimo and maximo ) where $nivel = '$a' $valor and codigo = '$serie[$i]'";
				
				if($nivel=="1" && $a=="1" )
				{
					$a=$nivelD[$x]->zona;
					$nivel="zona";
					$sql.=" and clues In (".$zonaClues["$a"].")";
				}
				
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
				// array para el empaquetado de los datos y poder pintar con la libreria js-chart en angular
				$highlightFill=explode(",",$colorInd[$i]);
				$highlightFil2=explode(",",$color);
				$data["datasets"][$i]["fillColor"]=$highlightFill[0].",".$highlightFill[1].",".$highlightFill[2].",0)";
				$data["datasets"][$i]["strokeColor"]=$highlightFill[0].",".$highlightFill[1].",".$highlightFill[2].",1)";
				$data["datasets"][$i]["pointColor"]=$colorInd[$i];
				$data["datasets"][$i]["pointStrokeColor"]=$color;
				$data["datasets"][$i]["pointHighlightFill"]=$highlightFill[0].",".$highlightFill[1].",".$highlightFill[2].",0.50)";
				$data["datasets"][$i]["pointHighlightStroke"]=$highlightFil2[0].",".$highlightFil2[1].",".$highlightFil2[2].",0.50)";
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
	 * Recupera las dimensiones para el filtrado del gráfico Calidad.
	 *
	 * @param  $request
	 *			campo = campo de la base de datos donde recuperar el dato
	 *		    valor = valor de la variable nivel
	 *			nivel  = espacio tiempo
	 * Response si se puede crear json con los datos y estado 200 si no error y estado 404
	 * @return Response
	 */
	public function indicadorCalidadDimension()
	{
		$datos = Request::all();
		$campo = $datos["campo"];
		$valor = $datos["valor"];
		$nivel = $datos["nivel"];
		
		if($nivel=="anio"||$nivel=="month"||$nivel=="jurisdiccion")
			Session::forget('cluesUsuario');
		if (Session::has('cluesUsuario'))
			$cluesUsuario=Session::get('cluesUsuario');
		else
			$cluesUsuario=$this->permisoZona();
		
		if($nivel=="zona")
		{
			$parametro = $datos["parametro"];
			$nivelD = DB::select("select distinct z.id,z.nombre from ZonaClues zc 
								LEFT JOIN Zona z on z.id=zc.idZona 
								WHERE zc.clues in ($cluesUsuario) and zc.jurisdiccion='$parametro'");
			$zonas = array();
			foreach($nivelD as $item)			
				array_push($zonas,$item->id);
			
			$zonas=implode(",",$zonas);
			$result = DB::select("select distinct clues from ZonaClues where idZona in ($zonas)");
			$cluesUsuario=array();
			foreach($result as $item)			
				array_push($cluesUsuario,"'".$item->clues."'");
			$cluesUsuario=implode(",",$cluesUsuario);
			
			Session::put('cluesUsuario', $cluesUsuario);
		}
		if($nivel!="zona")
			$nivelD = DB::select("select distinct $nivel from Calidad where clues in ($cluesUsuario) $valor");
		
		if($nivel=="month")
		{
			Session::forget('cluesUsuario');
			$nivelD=$this->getBimestre($nivelD);			
		}
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
	 * Muestra las evaluaciones que correspondan con el ultimo nivel de filtrado.
	 *
	 * @param  $request
	 *			anio 
	 *		    mes 
	 *			clues  
	 * Response si se puede crear json con los datos y estado 200 si no error y estado 404
	 * @return Response
	 */
	public function indicadorCalidadClues()
	{
		$datos = Request::all();
		$anio = isset($datos["anio"]) ? $datos["anio"] : '';
		$mes = isset($datos["mes"]) ? $datos["mes"] : '';
		$clues = isset($datos["clues"]) ? $datos["clues"] : '';
		
		$cluesUsuario=$this->permisoZona();
		$sql="select distinct codigo,indicador from Calidad where anio='$anio' and clues='$clues' and clues in ($cluesUsuario) ";
		if(!strpos($mes,"and"))
			$sql.="and month='$mes'";
		else
			$sql.="and mes between $mes ";
	
		$sql.="order by indicador";
		$indicadores = DB::select($sql);
		$cols=[];$serie=[];
		foreach($indicadores as $item)
		{
			array_push($serie,$item->codigo);
		}
		$sql="select distinct evaluacion from Calidad where anio='$anio' and clues='$clues' and clues in ($cluesUsuario)";
		if(!strpos($mes,"and"))
			$sql.="and month='$mes'";
		else
			$sql.="and mes between $mes ";
		$nivelD = DB::select($sql);
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
				(Calidad.promedio) between minimo and maximo ) where anio='$anio' and clues='$clues' and codigo = '$serie[$i]'";
				
				if(!strpos($mes,"and"))
					$sql.="and month='$mes'";
				else
					$sql.="and mes between $mes ";
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
				$data["datasets"][$i]["fillColor"]=$highlightFill[0].",".$highlightFill[1].",".$highlightFill[2].",0)";
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
	 * Muestra una lista de los recurso para el gráfico indicadores en alerta.
	 *
	 * @param  $request
	 *          tipo = especifica si el gráfico es de abasto o calidad
	 *			campo = campo de la base de datos donde recuperar el dato
	 *		    valor = valor de la variable nivel
	 *			nivel  = espacio tiempo
	 * Response si se puede crear json con los datos y estado 200 si no error y estado 404
	 * @return Response
	 */
	public function alerta()
	{
		$datos = Request::all();		
		$tipo = $datos["tipo"];
		if(isset($datos["campo"]))
		{
			$campo = $datos["campo"];
			$valor = $datos["valor"];
			$nivel = $datos["nivel"];
			
			if($nivel=="anio"||$nivel=="month"||$nivel=="jurisdiccion")
				Session::forget('cluesUsuario');
			if (Session::has('cluesUsuario'))
				$cluesUsuario=Session::get('cluesUsuario');
			else
				$cluesUsuario=$this->permisoZona();
			
			if($nivel=="zona")
			{
				$parametro = $datos["parametro"];
				$nivelD = DB::select("select distinct z.id,z.nombre from ZonaClues zc 
									LEFT JOIN Zona z on z.id=zc.idZona 
									WHERE zc.clues in ($cluesUsuario) and zc.jurisdiccion='$parametro'");
				$zonas = array();
				foreach($nivelD as $item)			
					array_push($zonas,$item->id);
				
				$zonas=implode(",",$zonas);
				$result = DB::select("select distinct clues from ZonaClues where idZona in ($zonas)");
				$cluesUsuario=array();
				foreach($result as $item)			
					array_push($cluesUsuario,"'".$item->clues."'");
				$cluesUsuario=implode(",",$cluesUsuario);
				
				Session::put('cluesUsuario', $cluesUsuario);
			}
			
			$sql="select distinct codigo,indicador from $tipo where clues in ($cluesUsuario) $valor order by indicador";
		}
		if(isset($datos["anio"]))
		{
			$anio = isset($datos["anio"]) ? $datos["anio"] : '';
			$mes = isset($datos["mes"]) ? $datos["mes"] : '';
			$clues = isset($datos["clues"]) ? $datos["clues"] : '';
			$valor = " and clues = '$clues' and anio='$anio' and mes between $mes";
			$sql="select distinct codigo,indicador from $tipo where clues = '$clues' and anio='$anio' and mes between $mes order by indicador";
		}
				
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
					(((aprobado+noAplica)/total)*100) between minimo and maximo ) where indicador = '$serie[$i]'";
			}
			
			if($tipo=="Calidad")
			{
				$sql="select Calidad.id,indicador,total,Calidad.promedio as porcentaje, 
					a.color, fechaEvaluacion,dia,mes,anio,day,month,semana,clues,Calidad.nombre,cone from Calidad 
					left join Alerta a on a.id=(select idAlerta from IndicadorAlerta where idIndicador=Calidad.id and 
					(Calidad.promedio) between minimo and maximo ) where indicador = '$serie[$i]'";
			}
			
			$sql.=" $valor";
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
	 * Muestra una lista de los recurso para el gráfico total de datos recolectados por equipo.
	 *
	 * @param  $request
	 *          tipo = especifica si el gráfico es de abasto o calidad
	 *			campo = campo de la base de datos donde recuperar el dato
	 *		    valor = valor de la variable nivel
	 *			nivel  = espacio tiempo
	 * Response si se puede crear json con los datos y estado 200 si no error y estado 404
	 * @return Response
	 */
	public function hallazgoGauge()
	{
		$datos = Request::all();
		$campo = isset($datos["campo"]) ? $datos["campo"] : "";
		$valor = isset($datos["valor"]) ? $datos["valor"] : "";
		$nivel = isset($datos["nivel"]) ? $datos["nivel"] : "";
		$tipo = $datos["tipo"];
		$anio = $datos["anio"];
		$mes = $datos["mes"];
		$clues = $datos["clues"];
		
		if($nivel=="anio"||$nivel=="month"||$nivel=="jurisdiccion"||$nivel=="")
			Session::forget('cluesUsuario');
		if (Session::has('cluesUsuario'))
			$cluesUsuario=Session::get('cluesUsuario');
		else
			$cluesUsuario=$this->permisoZona();
		
		if($nivel=="clues")
		{			
			$parametro = $datos["parametro"];
			$nivelD = DB::select("select distinct zc.clues from ZonaClues zc 
								LEFT JOIN Zona z on z.id=zc.idZona 
								WHERE zc.clues in ($cluesUsuario) and z.nombre='$parametro' or z.id='$parametro'");
			$cluesUsuario=array();
			foreach($nivelD as $item)			
				array_push($cluesUsuario,"'".$item->clues."'");
			$cluesUsuario=implode(",",$cluesUsuario);
		}
		if($nivel=="zona")
		{
			$parametro = $datos["parametro"];
			$nivelD = DB::select("select distinct z.id,z.nombre, z.nombre as zona from ZonaClues zc 
								LEFT JOIN Zona z on z.id=zc.idZona 
								WHERE zc.clues in ($cluesUsuario) and zc.jurisdiccion='$parametro'");
			$zonas = array();
			$zonaClues = array();
			foreach($nivelD as $item)
			{			
				array_push($zonas,$item->id);
				$res = DB::select("select distinct clues from ZonaClues where idZona='$item->id'");
				$x=array();
				foreach($res as $i)			
					array_push($x,"'".$i->clues."'");
				$zonaClues["$item->nombre"] = implode(",",$x);
			}
			
			$zonas=implode(",",$zonas);
			$result = DB::select("select distinct clues from ZonaClues where idZona in ($zonas)");
			$cluesUsuario=array();
			foreach($result as $item)			
				array_push($cluesUsuario,"'".$item->clues."'");
			$cluesUsuario=implode(",",$cluesUsuario);
			
			Session::put('cluesUsuario', $cluesUsuario);
		}
		
		$sql="SELECT count(sh.id) as total, (select count(id) from SeguimientoHallazgo where resuelto=1 and categoria='$tipo') as resuelto FROM SeguimientoHallazgo sh";
		if($tipo=="Abasto")
		{
			$sql.=" LEFT JOIN Evaluacion e on e.id = sh.idEvaluacion";
		}
		if($tipo=="Calidad")
		{
			$sql.=" LEFT JOIN EvaluacionCalidad e on e.id = sh.idEvaluacion";
		}
		$sql.=" LEFT JOIN Clues c on c.clues = e.clues
				LEFT JOIN ConeClues cc on cc.clues = c.clues
				LEFT JOIN Cone cn on cn.id = cc.idCone
				where  e.borradoAl is null and sh.categoria='$tipo' and c.clues in ($cluesUsuario) $valor";
				
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
						
			$ord = $tipo == 'Abasto' ? 'ec.id' : 'ec.expediente';
			$sql="SELECT i.codigo, i.nombre,i.id FROM Indicador i ";
			if($tipo=="Abasto")
			{
				$sql.=" LEFT JOIN EvaluacionCriterio ec on  ec.idIndicador=i.id
						LEFT JOIN Evaluacion e on e.id=ec.idEvaluacion";
			}
			if($tipo=="Calidad")
			{
				$sql.=" LEFT JOIN EvaluacionCalidadRegistro ec on  ec.idIndicador=i.id
						LEFT JOIN EvaluacionCalidad e on e.id=ec.idEvaluacionCalidad";
			}
			
			$sql.=" WHERE i.borradoAl is null and e.borradoAl is null and i.categoria='$tipo'";
			
			
			if($anio!="")
				$sql.=" and YEAR(e.fechaEvaluacion)='$anio'";
			if($mes!="")
				$sql.=" and MONTH(e.fechaEvaluacion) between  $mes";
			if($clues!="")
				$sql.=" and e.clues='$clues'";
			
			$sql.=" order by i.codigo, e.id, $ord";
			
			$result = DB::select($sql);
			$temp = ""; $i = 0;
			$data=array();
			if($result)
			{
				foreach($result as $item)
				{					
					if($temp != $item->codigo)
					{
						$col = $tipo == 'Abasto' ? "select count(distinct idEvaluacion) as total from EvaluacionCriterio where idIndicador='$item->id' " : "select count(distinct expediente) as total from EvaluacionCalidadRegistro where idIndicador='$item->id' ";
						$data[$i]["codigo"] = $item->codigo;
						$data[$i]["nombre"] = $item->nombre;
						$data[$i]["total"] = DB::select($col)[0]->total;
						$temp = $item->codigo;						
						$i++;
					}
				}
			}		
		
			return Response::json(array("status" => 200, "messages" => "ok", 
			"data"  => $data,
			"valor" => $resuelto,
			"rangos"=> $rangos,
			"total" => $total),200);
		}
	}
	
	
	/**
	 * Muestra una lista de los recurso para el gráfico global de calidad.
	 *
	 * @param  $request
	 *			campo = campo de la base de datos donde recuperar el dato
	 *		    valor = valor de la variable nivel
	 *			nivel  = espacio tiempo
	 * Response si se puede crear json con los datos y estado 200 si no error y estado 404
	 * @return Response
	 */
	public function indicadorCalidadGlobal()
	{
		$datos = Request::all();
		$campo = $datos["campo"];
		$valor = $datos["valor"];
		$nivel = $datos["nivel"];
		
		if($nivel=="anio"||$nivel=="month"||$nivel=="jurisdiccion"||$nivel=="")
			Session::forget('cluesUsuario');
		if (Session::has('cluesUsuario'))
			$cluesUsuario=Session::get('cluesUsuario');
		else
			$cluesUsuario=$this->permisoZona();
		
		if($nivel=="clues")
		{			
			$parametro = $datos["parametro"];
			$nivelD = DB::select("select distinct zc.clues from ZonaClues zc 
								LEFT JOIN Zona z on z.id=zc.idZona 
								WHERE zc.clues in ($cluesUsuario) and z.nombre='$parametro' or z.id='$parametro'");
			$cluesUsuario=array();
			foreach($nivelD as $item)			
				array_push($cluesUsuario,"'".$item->clues."'");
			$cluesUsuario=implode(",",$cluesUsuario);
		}
		if($nivel=="zona")
		{
			$parametro = $datos["parametro"];
			$nivelD = DB::select("select distinct z.id,z.nombre, z.nombre as zona from ZonaClues zc 
								LEFT JOIN Zona z on z.id=zc.idZona 
								WHERE zc.clues in ($cluesUsuario) and zc.jurisdiccion='$parametro'");
			$zonas = array();
			$zonaClues = array();
			foreach($nivelD as $item)
			{			
				array_push($zonas,$item->id);
				$res = DB::select("select distinct clues from ZonaClues where idZona='$item->id'");
				$x=array();
				foreach($res as $i)			
					array_push($x,"'".$i->clues."'");
				$zonaClues["$item->nombre"] = implode(",",$x);
			}
			
			$zonas=implode(",",$zonas);
			$result = DB::select("select distinct clues from ZonaClues where idZona in ($zonas)");
			$cluesUsuario=array();
			foreach($result as $item)			
				array_push($cluesUsuario,"'".$item->clues."'");
			$cluesUsuario=implode(",",$cluesUsuario);
			
			Session::put('cluesUsuario', $cluesUsuario);
		}		
		$sql="select distinct codigo,indicador from Calidad where clues in ($cluesUsuario) $valor order by codigo";
		
		$indicadores = DB::select($sql);
		
		$sql="select distinct clues,nombre from Calidad where clues in ($cluesUsuario) $valor";
		
		$data=false;
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
				where clues='$um' and codigo = '$codigo' $valor";
				
		
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
	
	
	/**
	 * Muestra una lista de los recurso para el gráfico de visitas.
	 *
	 * @param  $request
	 *          tipo = especifica si el gráfico es de abasto o calidad
	 *			campo = campo de la base de datos donde recuperar el dato
	 *		    valor = valor de la variable nivel
	 *			nivel  = espacio tiempo
	 * Response si se puede crear json con los datos y estado 200 si no error y estado 404
	 * @return Response
	 */
	public function pieVisita()
	{
		$datos = Request::all();
		
		$dimension = isset($datos["dimension"]) ? $datos["dimension"] : array();		
		$tipo = $datos["tipo"];
	
		if(count($dimension)==1||count($dimension)==2||$dimension=="")
			Session::forget('cluesUsuario');
		if (Session::has('cluesUsuario'))
			$cluesUsuario=Session::get('cluesUsuario');
		else
			$cluesUsuario=$this->permisoZona();
		
		if(count($dimension)==3)
		{
			$jurisdiccion=$dimension[2];
			$result = DB::select("select distinct clues from Clues where jurisdiccion = '$jurisdiccion' and clues in ($cluesUsuario)");
			$cluesUsuario=array();
			foreach($result as $item)			
				array_push($cluesUsuario,"'".$item->clues."'");
			$cluesUsuario=implode(",",$cluesUsuario);
			
			Session::put('cluesUsuario', $cluesUsuario);
		}
		if(count($dimension)==4)
		{
			$parametro = $dimension[2];
			$nivelD = DB::select("select distinct z.id,z.nombre, z.nombre as zona from ZonaClues zc 
								LEFT JOIN Zona z on z.id=zc.idZona 
								WHERE zc.clues in ($cluesUsuario) and zc.jurisdiccion='$parametro'");
			$zonas = array();
			$zonaClues = array();
			foreach($nivelD as $item)
			{			
				array_push($zonas,$item->id);
				$res = DB::select("select distinct clues from ZonaClues where idZona='$item->id'");
				$x=array();
				foreach($res as $i)			
					array_push($x,"'".$i->clues."'");
				$zonaClues["$item->nombre"] = implode(",",$x);
			}
			
			$zonas=implode(",",$zonas);
			$result = DB::select("select distinct clues from ZonaClues where idZona in ($zonas)");
			$cluesUsuario=array();
			foreach($result as $item)			
				array_push($cluesUsuario,"'".$item->clues."'");
			$cluesUsuario=implode(",",$cluesUsuario);
			
			Session::put('cluesUsuario', $cluesUsuario);
		}
		if(count($dimension)==5)
		{			
			$parametro = $dimension[4];
			$nivelD = DB::select("select distinct zc.clues from ZonaClues zc 
								LEFT JOIN Zona z on z.id=zc.idZona 
								WHERE zc.clues in ($cluesUsuario) and z.nombre='$parametro' or z.id='$parametro'");
			$cluesUsuario=array();
			foreach($nivelD as $item)			
				array_push($cluesUsuario,"'".$item->clues."'");
			$cluesUsuario=implode(",",$cluesUsuario);
		}		
		
		$totalClues=count(explode(",",$cluesUsuario));
		$sql="SELECT count(distinct clues) as total from $tipo where clues in ($cluesUsuario) ";
		if(count($dimension)>0)
			$sql .= " and anio='$dimension[0]'";
		if(count($dimension)>1)
			$sql .= " and mes between $dimension[1]";
		$data = DB::select($sql);
		
		if(!$data)
		{
			$data[0]=array(
			"value"=> 1,
			"color"=>'hsla(184, 0%, 24%, 0.62)',
			"highlight"=> 'hsla(184, 0%, 24%, 0.32)',
			"label"=> 'Selecciones opciones para mostrar datos');
			
			return Response::json(array("status" => 200, "messages" => "ok", 
			"data"  => $data,
			"total" => 0),200);
		} 
		else 
		{	/*		
			$mes=array();
			$mes[0]  = array("mes"=> 1,"month"=>"Enero");
			$mes[1]  = array("mes"=> 2,"month"=>"Febrero");
			$mes[2]  = array("mes"=> 3,"month"=>"Marzo");
			$mes[3]  = array("mes"=> 4,"month"=>"Abril");
			$mes[4]  = array("mes"=> 5,"month"=>"Mayo");
			$mes[5]  = array("mes"=> 6,"month"=>"Junio");
			$mes[6]  = array("mes"=> 7,"month"=>"Julio");
			$mes[7]  = array("mes"=> 8,"month"=>"Agosto");
			$mes[8]  = array("mes"=> 9,"month"=>"Septiembre");
			$mes[9]  = array("mes"=>10,"month"=>"Octubre");
			$mes[10] = array("mes"=>11,"month"=>"Noviembre");
			$mes[11] = array("mes"=>12,"month"=>"Diciembre");
			if(count($dimension)==0)
			{
				$nivelD = DB::select("select distinct anio from $tipo where clues in ($cluesUsuario)");
				$x=0;$temp=array();
				foreach($nivelD as $anio)
				{					
					for($i=0;$i<12;$i++)
					{
						$temp[$i+$x]=$mes[$i];
						$temp[$i+$x]["anio"]=$anio->anio;	
					}
					$x=$x+12;						
				}
				$mes=$temp;
			}
			
			if(count($dimension)==1)
			{
				for($i=0;$i<12;$i++)
						$mes[$i]["anio"]=$dimension[0];
			}
			if(count($dimension)>=2)
			{
				$temp=array();$a=0;
				for($i=0;$i<12;$i++)
				{
					if(strpos($dimension[1],($i+1)))
					{
						$a++;
						$temp[$a]=$mes[$i];
						$temp[$a]["anio"]=$dimension[0];
					}
				}
				$mes=$temp;
			}
			
			$clues = DB::select("select distinct c.clues,c.nombre from Clues c where c.clues in ($cluesUsuario)");	
			$dato=array();
			foreach($clues as $item)
			{
				$c=$item->clues;
				$n=$item->nombre;
				for($i=0;$i<count($mes);$i++)
				{
					$a=$mes[$i]["anio"];
					$m=$mes[$i]["mes"];
					
					$evaluado=DB::select("select clues from $tipo where anio = '$a' and mes = '$m' and clues='$c'");
					if($evaluado) $evaluado = 1; else $evaluado = 0;
					$dato[$i]=$mes[$i];
					$dato[$i]["clues"]=$c;
					$dato[$i]["nombre"]=$n;
					$dato[$i]["evaluado"]=$evaluado;
				}
				
			}*/
				
			$total=$data[0]->total;		
			$data[0]=array(
			"value"=> $totalClues-$total,
			"color"=>'hsla(1, 100%, 50%, 0.62)',
			"highlight"=> 'hsla(1, 100%, 50%, 0.32)',
			"label"=> 'No Visitado');
			$data[1]=array(
			"value"=> $total,
			"color"=>'hsla(107, 100%, 50%, 0.62)',
			"highlight"=> 'hsla(107, 100%, 50%, 0.32)',
			"label"=> 'Visitado');
		 
		
			return Response::json(array("status" => 200, "messages" => "ok", 
			"data"  => $data,
			"total" => $total),200);
		}
	}
	
	/**
	 * Obtener la lista de clues que el usuario tiene acceso.
	 *
	 * @param session sentry, usuario logueado
	 * Response si la operacion es exitosa devolver un array con el listado de clues
	 * @return array
	 */
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
			array_push($cluesUsuario,"'".$item->clues."'");
		}
		return implode(",",$cluesUsuario);
	}
	/**
	 * Obtener la lista del bimestre que corresponda un mes.
	 *
	 * @param $nivelD
	 * Response si la operacion es exitosa devolver un array con el listado de clues
	 * @return array
	 */
	public function getBimestre($nivelD)
	{
		$bimestre="";
		foreach($nivelD as $n)
		{
			$bimestre.=",".strtoupper($n->month);
		}
		$nivelD=array();
		if(strpos($bimestre,"JANUARY") || strpos($bimestre,"FEBRUARY"))
			array_push($nivelD,array("id" => "1 and 2" , "nombre" => "Enero -Febrero"));
		
		if(strpos($bimestre,"MARCH") || strpos($bimestre,"APRIL"))
			array_push($nivelD,array("id" => "3 and 4" , "nombre" => "Marzo - Abril"));
		
		if(strpos($bimestre,"MAY") || strpos($bimestre,"JUNE"))
			array_push($nivelD,array("id" => "5 and 6" , "nombre" => "Mayo - Junio"));
		
		if(strpos($bimestre,"JULY") || strpos($bimestre,"AUGUST"))
			array_push($nivelD,array("id" => "7 and 8" , "nombre" => "Julio - Agosto"));
		
		if(strpos($bimestre,"SEPTEMBER") || strpos($bimestre,"OCTOBER"))
			array_push($nivelD,array("id" => "9 and 10" , "nombre" => "Septiembre - Octubre"));
		
		if(strpos($bimestre,"NOVEMBER") || strpos($bimestre,"DECEMBER"))
			array_push($nivelD,array("id" => "10 and 11" , "nombre" => "Noviembre - Diciembre"));
		
		return $nivelD;
	}
}
?>