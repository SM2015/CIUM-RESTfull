<?php namespace App\Http\Controllers\Catalogos;

use App\Http\Requests;
use App\Http\Controllers\Controller;

use Request;
use Response;
use Input;
use DB; 
use Event;
use App\Models\Catalogos\Indicador;
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
			if($pagina == 0)
			{
				$pagina = 1;
			}
			if(array_key_exists('buscar',$datos))
			{
				$columna = $datos['columna'];
				$valor   = $datos['valor'];
				$indicador = Indicador::where($columna, 'LIKE', '%'.$valor.'%')->skip($pagina-1)->take($datos->get('limite'))->get();
			}
			else
			{
				$indicador = Indicador::skip($pagina-1)->take($datos['limite'])->get();
			}
			$total=Indicador::all();
		}
		else
		{
			$indicador = Indicador::all();
			$total=$indicador;
		}

		if(!$indicador)
		{
			return Response::json(array('status'=> 404,"messages"=>'No encontrado'),404);
		} 
		else 
		{
			return Response::json(array("status"=>200,"messages"=>"ok","value"=>$indicador,"total"=>count($total)),200);
			
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
            $indicador = new Indicador;
            $indicador->codigo = $datos->get('codigo');
			$indicador->nombre = $datos->get('nombre');

            if ($indicador->save()) 
                $success = true;
        } 
		catch (\Exception $e) 
		{
        }
        if ($success) 
		{
            DB::commit();
			return Response::json(array("status"=>201,"messages"=>"Creado","value"=>$indicador),201);
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
		$indicador = Indicador::find($id);

		if(!$indicador)
		{
			return Response::json(array('status'=> 404,"messages"=>'No encontrado'),404);
		} 
		else 
		{
			return Response::json(array("status"=>200,"messages"=>"ok","value"=>$indicador),200);
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
			$indicador = Indicador::find($id);
			$indicador->codigo = $datos->get('codigo');
			$indicador->nombre = $datos->get('nombre');

            if ($indicador->save()) 
                $success = true;
		} 
		catch (\Exception $e) 
		{
        }
        if ($success)
		{
			DB::commit();
			return Response::json(array("status"=>200,"messages"=>"ok","value"=>$indicador),200);
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
        }
        if ($success)
		{
			DB::commit();
			return Response::json(array("status"=>200,"messages"=>"ok","value"=>$indicador),200);
		} 
		else 
		{
			DB::rollback();
			return Response::json(array('status'=> 500,"messages"=>'Error interno del servidor'),500);
		}
	}

}
