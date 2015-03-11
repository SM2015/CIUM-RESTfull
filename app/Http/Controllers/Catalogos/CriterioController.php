<?php namespace App\Http\Controllers\Catalogos;

use App\Http\Requests;
use App\Http\Controllers\Controller;

use Request;
use Response;
use Input;
use DB;
use App\Models\Catalogos\Criterio;
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
			return Response::json(array("status"=>200,"messages"=>"ok","value"=>$criterio,"total"=>count($total)),200);
			
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
			return Response::json(array("status"=>201,"messages"=>"Creado","value"=>$criterio),201);
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
			return Response::json(array("status"=>200,"messages"=>"ok","value"=>$criterio),200);
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
			return Response::json(array("status"=>200,"messages"=>"ok","value"=>$criterio),200);
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
			return Response::json(array("status"=>200,"messages"=>"ok","value"=>$criterio),200);
		} 
		else 
		{
			DB::rollback();
			return Response::json(array('status'=> 500,"messages"=>'Error interno del servidor'),500);
		}
	}

}
