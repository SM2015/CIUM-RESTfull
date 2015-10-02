<?php
namespace App\Http\Controllers\v1\Transacciones;

use App\Http\Requests;
use App\Http\Controllers\Controller;

use Response;
use Input;
use DB;
use Session;
use Sentry;
use Request;

use App\Models\Catalogos\Clues;
use App\Models\Catalogos\ConeClues;

use App\Models\Transacciones\EvaluacionCalidadRegistro;
/**
* Controlador Dashboard
* 
* @package    CIUM API
* @subpackage Controlador
* @author     Eliecer Ramirez Esquinca <ramirez.esquinca@gmail.com>
* @created    2015-07-20

* Controlador `Dashboard`: Maneja los datos para mostrar en cada área del gráfico
*
*/
class DashboardController extends Controller 
{	
    /**
	 * Devuelve los resultados de la petición para el gráfico de Recursos.
	 *
	 * <Ul>Filtro avanzado
	 * <Li> <code>$filtro</code> json con los datos del filtro avanzado</ li>
	 * </Ul>
	 *		    
	 * Todo Los parametros son opcionales
	 * @return Response 
	 * <code style="color:green"> Respuesta Ok json(array("status": 200, "messages": "Operación realizada con exito", "data": array(resultado)),status) </code>
	 * <code> Respuesta Error json(array("status": 404, "messages": "No hay resultados"),status) </code>
	 */
	public function indicadorRecurso()
	{
		/**
		 * @var json $filtro contiene el json de los parametros
		 * @var string $datos recibe todos los parametros
		 * @var string $cluesUsuario contiene las clues por permiso del usuario
		 * @var string $parametro contiene los filtros procesados en query
		 * @var string $nivel muestra el dato de la etiqueta en el grafico
		 */
		$datos = Request::all();
		$filtro = array_key_exists("filtro",$datos) ? json_decode($datos["filtro"]) : null; 		
		$cluesUsuario=$this->permisoZona();
		
		$parametro = $this->getTiempo($filtro);
		$valor = $this->getParametro($filtro);
		$parametro .= $valor[0];
		$nivel = $valor[1];
		
		// validar la forma de visualizar el grafico por tiempo o por parametros
		if($filtro->visualizar=="tiempo")
			$nivel = "month";
		else 
		{
			if(array_key_exists("nivel",$filtro->um))
			{
				$nivel = $filtro->um->nivel;
				if($nivel == "clues")
				{
					$codigo = is_array($filtro->clues) ? implode("','",$filtro->clues) : $filtro->clues;
					if(is_array($filtro->clues))
						if(count($filtro->clues)>0)
							$cluesUsuario = "'".$codigo."'";					
				}
			}
		}
		// obtener las etiquetas del nivel de desglose
		$indicadores = array();
		$nivelD = DB::select("select distinct $nivel from Recurso where clues in ($cluesUsuario) $parametro order by anio,mes");
		$nivelDesglose=[];		
	
		for($x=0;$x<count($nivelD);$x++)
		{
			$a=$nivelD[$x]->$nivel;
			array_push($nivelDesglose,$a);
		}
		// todos los indicadores que tengan al menos una evaluación
		$indicadores = DB::select("select distinct color,codigo,indicador, 'Recurso' as categoriaEvaluacion from Recurso where clues in ($cluesUsuario) $parametro");
		$serie=[]; $colorInd=[];
		foreach($indicadores as $item)
		{
			array_push($serie,$item->indicador);
			array_push($colorInd,$item->color);
		}
		
		$color="";
		$a="";
		// recorrer los indicadores para obtener sus valores con respecto al filtrado		
		for($i=0;$i<count($serie);$i++)
		{
			$datos[$i] = [];
			$c=0;$porcentaje=0; $temp="";
			for($x=0;$x<count($nivelD);$x++)
			{
				$a=$nivelD[$x]->$nivel;				
				$data["datasets"][$i]["label"]=$serie[$i];
				$sql="select Recurso.id,indicador,total,(((aprobado+noAplica)/total)*100) as porcentaje, 
				a.color, fechaEvaluacion,dia,mes,anio,day,month,semana,clues,Recurso.nombre,cone from Recurso 
				left join Alerta a on a.id=(select idAlerta from IndicadorAlerta where idIndicador=Recurso.id and 
				(((aprobado+noAplica)/total)*100) between minimo and maximo ) where $nivel = '$a' and indicador = '$serie[$i]' $parametro";
				
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
					$porcentaje = number_format($porcentaje/$c, 2, ".", ",");
					$color=DB::select("select a.color from IndicadorAlerta ia 
					left join Alerta a on a.id=ia.idAlerta 
					where idIndicador=$indicador and ($porcentaje) between minimo and maximo")[0]->color;
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
			return Response::json(array("status"=> 404,"messages"=>"No hay resultados"),404);
		} 
		else 
		{
			return Response::json(array("status" => 200, "messages"=>"Operación realizada con exito", 
			"data" => $data, 
			"total" => count($data)),200);			
		}
	}
	
	/**
	 * Devuelve las dimensiones para los filtros de las opciones de recurso.
	 *
	 * <Ul>Filtro avanzado
	 * <Li> <code>$filtro</code> json con los datos del filtro avanzado</ li>
	 * </Ul>
	 *		    
	 * @return Response 
	 * <code style="color:green"> Respuesta Ok json(array("status": 200, "messages": "Operación realizada con exito", "data": array(resultado)),status) </code>
	 * <code> Respuesta Error json(array("status": 404, "messages": "No hay resultados"),status) </code>
	 */
	public function indicadorRecursoDimension()
	{
		$datos = Request::all();
		$filtro = array_key_exists("filtro",$datos) ? json_decode($datos["filtro"]) : null; 	
		$parametro = $this->getTiempo($filtro);
		$valor = $this->getParametro($filtro);
		$parametro .= $valor[0];
		$nivel = $datos["nivel"];
				
		$cluesUsuario=$this->permisoZona();
		
		$nivelD = DB::select("select distinct $nivel from Recurso where clues in ($cluesUsuario) $parametro");
		
		if($nivel=="month")
		{
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
			return Response::json(array("status"=> 404,"messages"=>"No hay resultados"),404);
		} 
		else 
		{
			return Response::json(array("status" => 200, "messages"=>"Operación realizada con exito", 
			"data" => $nivelD, 
			"total" => count($nivelD)),200);
		}
	}
	
	/**
	 * Devuelve el listado de evaluaciones de una unidad médica para el ultimo nivel del gráfico de Recursos.
	 *
	 * <h4>Request</h4>
	 * Request json $clues Clues de la unidad médica
	 * <Ul>Filtro avanzado
	 * <Li> <code>$filtro</code> json con los datos del filtro avanzado</ li>
	 * </Ul>
	 *		    
	 * @return Response 
	 * <code style="color:green"> Respuesta Ok json(array("status": 200, "messages": "Operación realizada con exito", "data": array(resultado)),status) </code>
	 * <code> Respuesta Error json(array("status": 404, "messages": "No hay resultados"),status) </code>
	 */
	public function indicadorRecursoClues()
	{
		$datos = Request::all();
		$clues = $datos["clues"];
		$filtro = array_key_exists("filtro",$datos) ? json_decode($datos["filtro"]) : null;
		$cluesUsuario=$this->permisoZona();
		
		$parametro = $this->getTiempo($filtro);				
			
		$sql="select distinct codigo,indicador,color from Recurso where clues='$clues' and clues in ($cluesUsuario) $parametro order by indicador";
		$indicadores = DB::select($sql);
		$cols=[];$serie=[]; $colorInd=[];
		foreach($indicadores as $item)
		{
			array_push($serie,$item->indicador);
			array_push($colorInd,$item->color);
		}
		$sql="select distinct evaluacion from Recurso where clues='$clues' and clues in ($cluesUsuario) $parametro";
		
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
				$sql="select Recurso.id,indicador,total,(((aprobado+noAplica)/total)*100) as porcentaje, 
				a.color, fechaEvaluacion,dia,mes,anio,day,month,semana,clues,Recurso.nombre,cone from Recurso 
				left join Alerta a on a.id=(select idAlerta from IndicadorAlerta where idIndicador=Recurso.id and 
				(((aprobado+noAplica)/total)*100) between minimo and maximo ) where clues='$clues' and indicador = '$serie[$i]' $parametro";
								
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
					$color=DB::select("select a.color from IndicadorAlerta ia 
					left join Alerta a on a.id=ia.idAlerta 
					where idIndicador=$indicador and ($porcentaje) between minimo and maximo")[0]->color;
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
			return Response::json(array('status'=> 404,"messages"=>'No hay resultados'),404);
		} 
		else 
		{
			return Response::json(array("status" => 200, "messages"=>"Operación realizada con exito", 
			"data" => $data, 
			"total" => count($data)),200);
			
		}
	}
	
	
	
	
	 /**
	 * Devuelve los resultados de la petición para el gráfico de Calidad.
	 *
	 * <Ul>Filtro avanzado
	 * <Li> <code>$filtro</code> json con los datos del filtro avanzado</ li>
	 * </Ul>
	 *		    
	 * Todo Los parametros son opcionales
	 * @return Response 
	 * <code style="color:green"> Respuesta Ok json(array("status": 200, "messages": "Operación realizada con exito", "data": array(resultado)),status) </code>
	 * <code> Respuesta Error json(array("status": 404, "messages": "No hay resultados"),status) </code>
	 */
	public function indicadorCalidad()
	{
		$datos = Request::all();
		$filtro = array_key_exists("filtro",$datos) ? json_decode($datos["filtro"]) : null; 		
		$cluesUsuario=$this->permisoZona();
		
		$parametro = $this->getTiempo($filtro);
		$valor = $this->getParametro($filtro);
		$parametro .= $valor[0];
		$nivel = $valor[1];
		
		// validar la forma de visualizar el grafico por tiempo o por parametros
		if($filtro->visualizar=="tiempo")
			$nivel = "month";
		else 
		{
			if(array_key_exists("nivel",$filtro->um))
			{
				$nivel = $filtro->um->nivel;
				if($nivel == "clues")
				{
					$codigo = is_array($filtro->clues) ? implode("','",$filtro->clues) : $filtro->clues;
					if(is_array($filtro->clues))
						if(count($filtro->clues)>0)
							$cluesUsuario = "'".$codigo."'";					
				}
			}
		}
		
		// obtener las etiquetas del nivel de desglose
		$indicadores = array();
		$nivelD = DB::select("select distinct $nivel from Calidad where clues in ($cluesUsuario) $parametro order by anio,mes");
		$nivelDesglose=[];		
	
		for($x=0;$x<count($nivelD);$x++)
		{
			$a=$nivelD[$x]->$nivel;
			array_push($nivelDesglose,$a);
		}
		// todos los indicadores que tengan al menos una evaluación		
		$indicadores = DB::select("select distinct color,codigo,indicador, 'Calidad' as categoriaEvaluacion from Calidad where clues in ($cluesUsuario) $parametro");
		$serie=[]; $colorInd=[];
		foreach($indicadores as $item)
		{
			array_push($serie,$item->indicador);
			array_push($colorInd,$item->color);
		}
		$color="";
		$a="";				
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
				(Calidad.promedio) between minimo and maximo ) where $nivel = '$a' and indicador = '$serie[$i]' $parametro";
								
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
					$porcentaje = number_format($porcentaje/$c, 2, ".", ",");
					$color=DB::select("select a.color from IndicadorAlerta ia 
					left join Alerta a on a.id=ia.idAlerta 
					where idIndicador=$indicador and ($porcentaje) between minimo and maximo")[0]->color;
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
			return Response::json(array("status"=> 404,"messages"=>"No hay resultados"),404);
		} 
		else 
		{
			return Response::json(array("status" => 200, "messages"=>"Operación realizada con exito", 
			"data" => $data, 
			"total" => count($data)),200);			
		}
	}
	
	/**
	 * Devuelve las dimensiones para los filtros de las opciones de calidad.
	 *
	 * <Ul>Filtro avanzado
	 * <Li> <code>$filtro</code> json con los datos del filtro avanzado</ li>
	 * </Ul>
	 *		    
	 * @return Response 
	 * <code style="color:green"> Respuesta Ok json(array("status": 200, "messages": "Operación realizada con exito", "data": array(resultado)),status) </code>
	 * <code> Respuesta Error json(array("status": 404, "messages": "No hay resultados"),status) </code>
	 */
	public function indicadorCalidadDimension()
	{
		$datos = Request::all();
		$filtro = array_key_exists("filtro",$datos) ? json_decode($datos["filtro"]) : null; 	
		$parametro = $this->getTiempo($filtro);
		$valor = $this->getParametro($filtro);
		$parametro .= $valor[0];
		$nivel = $datos["nivel"];
				
		$cluesUsuario=$this->permisoZona();
		
		$nivelD = DB::select("select distinct $nivel from Calidad where clues in ($cluesUsuario) $parametro");
		
		if($nivel=="month")
		{
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
			return Response::json(array("status"=> 404,"messages"=>"No hay resultados"),404);
		} 
		else 
		{
			return Response::json(array("status" => 200, "messages"=>"Operación realizada con exito", 
			"data" => $nivelD, 
			"total" => count($nivelD)),200);
		}
	}
	
	/**
	 * Devuelve el listado de evaluaciones de una unidad médica para el ultimo nivel del gráfico de Calidad.
	 *
	 * <h4>Request</h4>
	 * Request json $clues Clues de la unidad médica
	 * <Ul>Filtro avanzado
	 * <Li> <code>$filtro</code> json con los datos del filtro avanzado</ li>
	 * </Ul>
	 *		    
	 * @return Response 
	 * <code style="color:green"> Respuesta Ok json(array("status": 200, "messages": "Operación realizada con exito", "data": array(resultado)),status) </code>
	 * <code> Respuesta Error json(array("status": 404, "messages": "No hay resultados"),status) </code>
	 */
	public function indicadorCalidadClues()
	{
		$datos = Request::all();
		$clues = $datos["clues"];
		$filtro = array_key_exists("filtro",$datos) ? json_decode($datos["filtro"]) : null;
		$cluesUsuario=$this->permisoZona();
		
		$parametro = $this->getTiempo($filtro);
		$sql="select distinct codigo,indicador,color from Calidad where clues='$clues' and clues in ($cluesUsuario) $parametro";
		
		$sql.="order by indicador";
		$indicadores = DB::select($sql);
		$cols=[];$serie=[]; $colorInd=[];
		foreach($indicadores as $item)
		{
			array_push($serie,$item->indicador);
			array_push($colorInd,$item->color);
		}
		$sql="select distinct evaluacion from Calidad where clues='$clues' and clues in ($cluesUsuario) $parametro";
		
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
				(Calidad.promedio) between minimo and maximo ) where clues='$clues' and indicador = '$serie[$i]' $parametro";
								
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
					$color=DB::select("select a.color from IndicadorAlerta ia 
					left join Alerta a on a.id=ia.idAlerta 
					where idIndicador=$indicador and ($porcentaje) between minimo and maximo")[0]->color;
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
			return Response::json(array('status'=> 404,"messages"=>'No hay resultados'),404);
		} 
		else 
		{
			return Response::json(array("status" => 200, "messages"=>"Operación realizada con exito", 
			"data" => $data, 
			"total" => count($data)),200);
			
		}
	}
	
	/**
	 * Devuelve los datos para mostrar las alertas por indicador.
	 *
	 * <Ul>Filtro avanzado
	 * <Li> <code>$filtro</code> json con los datos del filtro avanzado</ li>
	 * </Ul>
	 *		    
	 * @return Response 
	 * <code style="color:green"> Respuesta Ok json(array("status": 200, "messages": "Operación realizada con exito", "data": array(resultado)),status) </code>
	 * <code> Respuesta Error json(array("status": 404, "messages": "No hay resultados"),status) </code>
	 */
	public function alerta()
	{
		$datos = Request::all();		
		$filtro = array_key_exists("filtro",$datos) ? json_decode($datos["filtro"]) : null;
		$tipo = $filtro->tipo;
		$cluesUsuario=$this->permisoZona();
		
		$parametro = $this->getTiempo($filtro);
		$valor = $this->getParametro($filtro);
		$parametro .= $valor[0];
		$nivel = $valor[1];	
				
		$sql="select distinct codigo,indicador from $tipo where clues in ($cluesUsuario) $parametro order by indicador";			
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
			if($tipo=="Recurso")
			{
				$sql="select Recurso.id,indicador,total,(((aprobado+noAplica)/total)*100) as porcentaje, 
					a.color, fechaEvaluacion,dia,mes,anio,day,month,semana,clues,Recurso.nombre,cone from Recurso 
					left join Alerta a on a.id=(select idAlerta from IndicadorAlerta where idIndicador=Recurso.id and 
					(((aprobado+noAplica)/total)*100) between minimo and maximo ) where indicador = '$serie[$i]'";
			}
			
			if($tipo=="Calidad")
			{
				$sql="select Calidad.id,indicador,total,Calidad.promedio as porcentaje, 
					a.color, fechaEvaluacion,dia,mes,anio,day,month,semana,clues,Calidad.nombre,cone from Calidad 
					left join Alerta a on a.id=(select idAlerta from IndicadorAlerta where idIndicador=Calidad.id and 
					(Calidad.promedio) between minimo and maximo ) where indicador = '$serie[$i]'";
			}
			
			$sql.=" $parametro";
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
				$color=DB::select("select a.color from IndicadorAlerta ia 
				left join Alerta a on a.id=ia.idAlerta 
				where idIndicador=$indicador and ($porcentaje) between minimo and maximo")[0]->color;
				 array_push($data,array("codigo" => $codigo[$i],"nombre" => $serie[$i],"color" => $color, "porcentaje" => $porcentaje));													
			}
			else array_push($data,array("codigo" => $codigo[$i],"nombre" => $serie[$i],"color" => "#357ebd", "porcentaje" => "N/A"));
		}
		if(!$data)
		{
			return Response::json(array('status'=> 404,"messages"=>'No hay resultados'),200);
		} 
		else 
		{
			return Response::json(array("status" => 200, "messages"=>"Operación realizada con exito", 
			"data" => $data, 
			"total" => count($data)),200);
		}
	}
	
	/**
	 * Devuelve los datos para las graficas tipo gauge.
	 *
	 * <Ul>Filtro avanzado
	 * <Li> <code>$filtro</code> json con los datos del filtro avanzado</ li>
	 * </Ul>
	 *		    
	 * @return Response 
	 * <code style="color:green"> Respuesta Ok json(array("status": 200, "messages": "Operación realizada con exito", "data": array(resultado)),status) </code>
	 * <code> Respuesta Error json(array("status": 404, "messages": "No hay resultados"),status) </code>
	 */
	public function hallazgoGauge()
	{
		$datos = Request::all();		
		$filtro = array_key_exists("filtro",$datos) ? json_decode($datos["filtro"]) : null;
		$tipo = $filtro->tipo;
		$cluesUsuario=$this->permisoZona();
		
		$parametro = $this->getTiempo($filtro);
		$valor = $this->getParametro($filtro);
		$parametro .= $valor[0];
		$nivel = $valor[1];			
		
		$sql=""; $sql0="";
		$sql1="SELECT distinct count(sh.clues) as total FROM  ConeClues sh where sh.clues in ($cluesUsuario)";
			
		$sql2="SELECT count(codigo) as resuelto FROM Hallazgos sh where categoria='$tipo' and codigo in(SELECT codigo FROM   ";
		$sql3="SELECT distinct codigo,color,indicador FROM Hallazgos sh where categoria='$tipo' and codigo in(SELECT codigo FROM   ";
		
		
		if($tipo=="Recurso")
		{
			$sql0.="Recurso where codigo = sh.codigo and noAprobado=0)";
		}
		if($tipo=="Calidad")
		{
			$sql0.="Calidad where codigo = sh.codigo and promedio<80)";
		}
		$sql.=" and sh.clues in ($cluesUsuario) $parametro";
				
		$data = DB::select($sql1);
		
		if(!$data)
		{
			return Response::json(array('status'=> 404,"messages"=>'No hay resultados'),404);
		} 
		else 
		{		
			$data2 = DB::select($sql2.$sql0.$sql);
			$data3 = DB::select($sql3.$sql0.$sql);
			$resuelto = $data2[0]->resuelto;
			$total = $data[0]->total;
			
			$rojo = ($total*.25);
			$nara = ($total*.5);
			$amar = ($total*.75);
			$verd = $total;
			
			$rangos[0] = array('min' => 0,     'max' => $rojo, 'color' => '#DDD');
			$rangos[1] = array('min' => $rojo, 'max' => $nara, 'color' => '#FDC702');
			$rangos[2] = array('min' => $nara, 'max' => $amar, 'color' => '#FF7700');
			$rangos[3] = array('min' => $amar, 'max' => $verd, 'color' => '#C50200');
						
						
		
			return Response::json(array("status" => 200, "messages"=>"Operación realizada con exito", 
			"data"  => $data,
			"valor" => $resuelto,
			"rangos"=> $rangos,
			"indicadores" => $data3,
			"total" => $total),200);
		}
	}
	
	
	/**
	 * Devuelve el TOP de las evaluaciones de calidad.
	 *
	 * <Ul>Filtro avanzado
	 * <Li> <code>$filtro</code> json con los datos del filtro avanzado</ li>
	 * </Ul>
	 *		    
	 * @return Response 
	 * <code style="color:green"> Respuesta Ok json(array("status": 200, "messages": "Operación realizada con exito", "data": array(resultado)),status) </code>
	 * <code> Respuesta Error json(array("status": 404, "messages": "No hay resultados"),status) </code>
	 */
	public function topCalidadGlobal()
	{
		$datos = Request::all();		
		$filtro = array_key_exists("filtro",$datos) ? json_decode($datos["filtro"]) : null;
		$top = array_key_exists("top",$filtro) ? $filtro->top : 5;
		$tipo = $filtro->tipo;
		$cluesUsuario=$this->permisoZona();
		
		$parametro = $this->getTiempo($filtro);
		$valor = $this->getParametro($filtro);
		$parametro .= $valor[0];
		$nivel = $valor[1];	
			
		$sql="((select sum(promedio) from Calidad where clues = c.clues)/(select count(promedio) from Calidad where clues = c.clues))";
		$sql1="select distinct clues,nombre, $sql as porcentaje from Calidad c where clues in ($cluesUsuario) $parametro and promedio between 80 and 100 order by $sql desc limit 0,$top";						
		$sql2="select distinct clues,nombre, $sql as porcentaje from Calidad c where clues in ($cluesUsuario) $parametro and promedio between 0 and 80  order by $sql asc limit 0,$top";
										
		$data["TOP_MAS"] = DB::select($sql1);
		$data["TOP_MENOS"] = DB::select($sql2);
		
		if(!$data)
		{
			return Response::json(array('status'=> 404,"messages"=>'No hay resultados'),404);
		} 
		else 
		{
			return Response::json(array("status" => 200, "messages"=>"Operación realizada con exito", 
			"data" => $data, 
			"total" => count($data)),200);			
		}
	}
	
	/**
	 * Devuelve TOP de las evaluaciones de recurso.
	 *
	 * <Ul>Filtro avanzado
	 * <Li> <code>$filtro</code> json con los datos del filtro avanzado</ li>
	 * </Ul>
	 *		    
	 * @return Response 
	 * <code style="color:green"> Respuesta Ok json(array("status": 200, "messages": "Operación realizada con exito", "data": array(resultado)),status) </code>
	 * <code> Respuesta Error json(array("status": 404, "messages": "No hay resultados"),status) </code>
	 */
	public function topRecursoGlobal()
	{
		$datos = Request::all();		
		$filtro = array_key_exists("filtro",$datos) ? json_decode($datos["filtro"]) : null;
		$top = array_key_exists("top",$filtro) ? $filtro->top : 5;
		$tipo = $filtro->tipo;
		$cluesUsuario=$this->permisoZona();
		
		$parametro = $this->getTiempo($filtro);
		$valor = $this->getParametro($filtro);
		$parametro .= $valor[0];
		$nivel = $valor[1];						
		
		$sql = "((select sum(aprobado) from Recurso where clues = r.clues)/(select sum(total) from Recurso where clues = r.clues))*100";
		$sql1="select distinct clues,nombre, $sql as porcentaje from Recurso r where clues in ($cluesUsuario) $parametro and $sql between 80 and 100 order by $sql desc limit 0,$top";		
		$sql2="select distinct clues,nombre, $sql as porcentaje from Recurso r where clues in ($cluesUsuario) $parametro and $sql between 0 and 80 order by $sql asc limit 0,$top ";		
		$data["TOP_MAS"] = DB::select($sql1);
		$data["TOP_MENOS"] = DB::select($sql2);
		
		if(!$data)
		{
			return Response::json(array('status'=> 404,"messages"=>'No hay resultados'),404);
		} 
		else 
		{
			return Response::json(array("status" => 200, "messages"=>"Operación realizada con exito", 
			"data" => $data, 
			"total" => count($data)),200);			
		}
	}
	
	
	/**
	 * Devuelve los datos para generar el gráfico tipo Pie de las evaluaciones recurso y calidad.
	 *
	 * <Ul>Filtro avanzado
	 * <Li> <code>$filtro</code> json con los datos del filtro avanzado</ li>
	 * </Ul>
	 *		    
	 * @return Response 
	 * <code style="color:green"> Respuesta Ok json(array("status": 200, "messages": "Operación realizada con exito", "data": array(resultado)),status) </code>
	 * <code> Respuesta Error json(array("status": 404, "messages": "No hay resultados"),status) </code>
	 */
	public function pieVisita()
	{
		$datos = Request::all();		
		$filtro = array_key_exists("filtro",$datos) ? json_decode($datos["filtro"]) : null;
		$tipo = $filtro->tipo;
		$cluesUsuario=$this->permisoZona();
		
		$parametro = $this->getTiempo($filtro);
		$valor = $this->getParametro($filtro);
		$parametro .= $valor[0];
		$nivel = $valor[1];	
					
		$totalClues=count(explode(",",$cluesUsuario));
		$sql="SELECT count(distinct clues) as total from $tipo where clues in ($cluesUsuario) $parametro";
		
		$data = DB::select($sql);
		
		if(!$data)
		{
			$data[0]=array(
			"value"=> 1,
			"color"=>'hsla(184, 0%, 24%, 0.62)',
			"highlight"=> 'hsla(184, 0%, 24%, 0.32)',
			"label"=> 'Selecciones opciones para mostrar datos');
			
			return Response::json(array("status" => 200, "messages"=>"Operación realizada con exito", 
			"data"  => $data,
			"total" => 0),200);
		} 
		else 
		{	
				
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
		 
		
			return Response::json(array("status" => 200, "messages"=>"Operación realizada con exito", 
			"data"  => $data,
			"total" => $total),200);
		}
	}
	
	/**
	 * Obtener la lista de clues que el usuario tiene acceso.
	 *
	 * get session sentry, usuario logueado
	 * Response si la operacion es exitosa devolver un string con las clues separadas por coma
	 * @return string	 
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
	 * @param string $nivelD que corresponde al numero del mes
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
	
	/**
	 * Genera los filtros de tiempo para el query.
	 *
	 * @param json $filtro Corresponde al filtro 
	 * @return string
	 */
	public function getTiempo($filtro)
	{
		/**		 
		 * @var string $cluesUsuario contiene las clues por permiso del usuario
		 *	 
		 * @var array $anio array con los años para filtrar
		 * @var array $bimestre bimestre del año a filtrar
		 * @var string $de si se quiere hacer un filtro por fechas este marca el inicio
		 * @var string $hasta si se quiere hacer un filtro por fechas este marca el final
		 */
					
		$anio = array_key_exists("anio",$filtro) ? is_array($filtro->anio) ? implode(",",$filtro->anio) : $filtro->anio : date("Y");
		$bimestre = array_key_exists("bimestre",$filtro) ? $filtro->bimestre : 'todos';		
		$de = array_key_exists("de",$filtro) ? $filtro->de : '';
		$hasta = array_key_exists("hasta",$filtro) ? $filtro->hasta : '';
		
		// procesamiento para los filtros de tiempo
		if($de != "" && $hasta != "")
		{
			$de = date("Y-m-d", strtotime($de));
			$hasta = date("Y-m-d", strtotime($hasta));
			$parametro = " and fechaEvaluacion between '$de' and '$hasta'";
		}
		else
		{
			if($anio != "todos")
				$parametro = " and anio in($anio)";
			else $parametro="";
			
			if($bimestre != "todos")
			{
				if(is_array($bimestre))
				{
					$parametro .= " and ";
					foreach($bimestre as $item)
					{
						 $parametro .= " mes between $item or";
					}
					$parametro .= " 1=1";
				}
				else{
					$parametro .= " and mes between $bimestre";
				}
			}
		}
		return $parametro;
	}
	
	/**
	 * Genera los filtros de parametro para el query.
	 *
	 * @param json $filtro Corresponde al filtro 
	 * @return string
	 */
	public function getParametro($filtro)
	{		
		// si trae filtros contruir el query	
		$parametro="";$nivel = "month";
		$verTodosIndicadores = array_key_exists("verTodosIndicadores",$filtro) ? $filtro->verTodosIndicadores : true;		
		if(!$verTodosIndicadores)
		{
			$nivel = "month";
			if(array_key_exists("indicador",$filtro))
			{
				$codigo = is_array($filtro->indicador) ? implode("','",$filtro->indicador) : $filtro->indicador;
				if(is_array($filtro->indicador))
					if(count($filtro->indicador)>0)
					{
						$codigo = "'".$codigo."'";
						$parametro .= " and codigo in($codigo)";	
					}						
			}
		}
		$verTodosUM = array_key_exists("verTodosUM",$filtro) ? $filtro->verTodosUM : true;
		if(!$verTodosUM)
		{
			if(array_key_exists("jurisdiccion",$filtro->um))
			{
				if(count($filtro->um->jurisdiccion)>1)
					$nivel = "jurisdiccion";
				else{
					if($filtro->um->tipo == "municipio")
						$nivel = "municipio";
					else
						$nivel = "zona";
				}
				$codigo = is_array($filtro->um->jurisdiccion) ? implode("','",$filtro->um->jurisdiccion) : $filtro->um->jurisdiccion;
				$codigo = "'".$codigo."'";
				$parametro .= " and jurisdiccion in($codigo)";
			}
			if(array_key_exists("municipio",$filtro->um)) 
			{
				if(count($filtro->um->municipio)>1)
					$nivel = "municipio";
				else
					$nivel = "clues";
				$codigo = is_array($filtro->um->municipio) ? implode("','",$filtro->um->municipio) : $filtro->um->municipio;
				$codigo = "'".$codigo."'";
				$parametro .= " and municipio in($codigo)";
			}
			if(array_key_exists("zona",$filtro->um)) 
			{
				if(count($filtro->um->zona)>1)
					$nivel = "zona";
				else
					$nivel = "clues";
				$codigo = is_array($filtro->um->zona) ? implode("','",$filtro->um->zona) : $filtro->um->zona;
				$codigo = "'".$codigo."'";
				$parametro .= " and zona in($codigo)";
			}
			if(array_key_exists("cone",$filtro->um)) 
			{
				if(count($filtro->um->cone)>1)
					$nivel = "cone";
				else
					$nivel = "jurisdiccion";
				$codigo = is_array($filtro->um->cone) ? implode("','",$filtro->um->cone) : $filtro->um->cone;
				$codigo = "'".$codigo."'";
				$parametro .= " and cone in($codigo)";
			}
		}
		return array($parametro,$nivel);
	}
}
?>