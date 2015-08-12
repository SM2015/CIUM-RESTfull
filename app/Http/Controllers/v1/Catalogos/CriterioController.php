<?php namespace App\Http\Controllers\v1\Catalogos;

use App\Http\Requests;
use App\Http\Controllers\Controller;

use Request;
use Response;
use Input;
use DB;
use App\Models\Catalogos\Criterio;
use App\Models\Catalogos\LugarVerificacion;
use App\Models\Catalogos\IndicadorCriterio;
use App\Models\Catalogos\ConeIndicadorCriterio;

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
			if(isset($datos['order']))
			{
				$order = $datos['order'];
				if(strpos(" ".$order,"-"))
					$orden="desc";
				else
					$orden="asc";
				$order=str_replace("-","",$order); 
			}
			else{
				$order="id"; $orden="asc";
			}
			
			if($pagina == 0)
			{
				$pagina = 1;
			}
			if(array_key_exists('buscar',$datos))
			{
				$columna = $datos['columna'];
				$valor   = $datos['valor'];
				$criterio = Criterio::with("Indicadores")->where($columna, 'LIKE', '%'.$valor.'%')->skip($pagina-1)->take($datos['limite'])->orderBy($order,$orden)->get();
				$total=$criterio;
			}
			else
			{
				$criterio = Criterio::with("Indicadores")->skip($pagina-1)->take($datos['limite'])->orderBy($order,$orden)->get();
				$total=Criterio::all();
			}
			
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
					->where('ci.idIndicadorCriterio' , $indicador->pivot->id )
					->where('ci.borradoAl' , null )
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
					$indicador->idLugarVerificacion = $i["lugarVerificacion"]["id"];
					
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
				->where('ci.idIndicadorCriterio' , $indicador->pivot->id )
				->where('ci.borradoAl' , null )
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
				$indicador = IndicadorCriterio::where("idCriterio", $criterio->id)->get();	
				foreach($indicador as $i)
				{
					$ic=IndicadorCriterio::find($i->id);
					$ic->delete();
					$cone = ConeIndicadorCriterio::where("idIndicadorCriterio", $ic->id)->get();
					foreach($cone as $c)
					{
						$cic=ConeIndicadorCriterio::find($c->id);
						$cic->delete();
					}
				}
				
				foreach($indicadores as $i)
				{
					$borrado = DB::table('IndicadorCriterio')							
					->where('idCriterio',$criterio->id)
					->where('idIndicador',$i["id"])
					->update(['borradoAl' => NULL]);
					$indicador = IndicadorCriterio::where("idCriterio", $criterio->id)->where("idIndicador", $i["id"])->first();
					if(!$indicador)
						$indicador = new IndicadorCriterio;
					$indicador->idCriterio = $criterio->id;
					$indicador->idIndicador = $i["id"];
					$indicador->idLugarVerificacion = $i["lugarVerificacion"]["id"];
					
					if ($indicador->save()) 
					{						
						foreach($i["cones"] as $c)
						{
							$borrado = DB::table('ConeIndicadorCriterio')							
							->where('idIndicadorCriterio',$indicador->id)
							->where('idCone',$c["id"])
							->update(['borradoAl' => NULL]);
							
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
			return Response::json(array('status'=> 500,"messages"=>'Error interno del servidor'),500);
		}
	}
}
