<?php namespace App\Http\Controllers\v1\Catalogos;

use App\Http\Requests;
use App\Http\Controllers\Controller;

use Request;
use Response;
use Input;
use DB; 
use Event;
use App\Models\Catalogos\Indicador;
use App\Models\Catalogos\IndicadorAlerta;
use App\Http\Requests\IndicadorRequest;


class IndicadorController extends Controller {

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
				$indicador = Indicador::with("IndicadorAlertas")->where($columna, 'LIKE', '%'.$valor.'%')->skip($pagina-1)->take($datos['limite'])->orderBy($order,$orden)->get();
				$total=$indicador;
			}
			else
			{
				$indicador = Indicador::with("IndicadorAlertas")->skip($pagina-1)->take($datos['limite'])->orderBy($order,$orden)->get();
				$total=Indicador::all();
			}
			
		}
		else
		{
			$indicador = Indicador::with("IndicadorAlertas");
			if(array_key_exists('categoria',$datos))
				$indicador = $indicador->where("categoria",$datos["categoria"]);
			$indicador = $indicador->get();
			$total=$indicador;
		}

		if(!$indicador)
		{
			return Response::json(array('status'=> 404,"messages"=>'No encontrado'),404);
		} 
		else 
		{
			return Response::json(array("status"=>200,"messages"=>"ok","data"=>$indicador,"total"=>count($total)),200);
			
		}
	}

	/**
	 * Store a newly created resource in storage.
	 *
	 * @return Response
	 */
	public function store(IndicadorRequest $request)
	{
		$datos = Input::json();
		$success = false;
        DB::beginTransaction();
        try 
		{
			$color = $datos->get('color');		
			if(count(explode(",",$color))<4)
				$color = "hsla".substr($datos->get('color'),3,strlen($datos->get('color'))-4)." 0.62)";
			
            $indicador = new Indicador;
            $indicador->codigo = $datos->get('codigo');
			$indicador->nombre = $datos->get('nombre');
			$indicador->categoria = $datos->get('categoria');
			$indicador->color = $color;

            if ($indicador->save())
			{	
				$alertas=$datos->get('indicador_alertas');
				for($i=0;$i<count($alertas);$i++)
				{
					$indicador_alertas =  new IndicadorAlerta;
					$indicador_alertas->minimo = $alertas[$i]["minimo"];
					$indicador_alertas->maximo = $alertas[$i]["maximo"];
					$indicador_alertas->idAlerta = $alertas[$i]["idAlerta"];
					$indicador_alertas->idIndicador = $indicador->id;
					$indicador_alertas->save();									
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
			return Response::json(array("status"=>201,"messages"=>"Creado","data"=>$indicador),201);
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
		$indicador = Indicador::with("IndicadorAlertas")->find($id);

		if(!$indicador)
		{
			return Response::json(array('status'=> 404,"messages"=>'No encontrado'),404);
		} 
		else 
		{
			return Response::json(array("status"=>200,"messages"=>"ok","data"=>$indicador),200);
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
			$color = $datos->get('color');		
			if(count(explode(",",$color))<4)
				$color = "hsla".substr($datos->get('color'),3,strlen($datos->get('color'))-4)." 0.62)";
			
			$indicador = Indicador::find($id);
			$indicador->codigo = $datos->get('codigo');
			$indicador->nombre = $datos->get('nombre');
			$indicador->categoria = $datos->get('categoria');
			$indicador->color = $color;
			
            if ($indicador->save())
			{					
				$alertas=$datos->get('indicador_alertas');

				for($i=0;$i<count($alertas);$i++)
				{
					$indicador_alertas = IndicadorAlerta::where('idIndicador',$indicador->id)->where('idAlerta',$alertas[$i]["idAlerta"])->first();
					if(!$indicador_alertas)						
						$indicador_alertas =  new IndicadorAlerta;
					
					$indicador_alertas->minimo = $alertas[$i]["minimo"];
					$indicador_alertas->maximo = $alertas[$i]["maximo"];
					$indicador_alertas->idAlerta = $alertas[$i]["idAlerta"];
					$indicador_alertas->idIndicador = $indicador->id;
					$indicador_alertas->save();									
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
			return Response::json(array("status"=>200,"messages"=>"ok","data"=>$indicador),200);
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
			$indicador = Indicador::find($id);
			$indicador->delete();
			$success=true;
		} 
		catch (\Exception $e) 
		{
			throw $e;
        }
        if ($success)
		{
			DB::commit();
			return Response::json(array("status"=>200,"messages"=>"ok","data"=>$indicador),200);
		} 
		else 
		{
			DB::rollback();
			return Response::json(array('status'=> 500,"messages"=>'Error interno del servidor'),500);
		}
	}

}
