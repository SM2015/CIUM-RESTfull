<?php namespace App\Http\Controllers\v1\Catalogos;

use App\Http\Requests;
use App\Http\Controllers\Controller;

use Request;
use Response;
use Input;
use DB; 
use App\Models\Catalogos\LugarVerificacion;
use App\Http\Requests\LugarVCRequest;


class LugarVerificacionController extends Controller {

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
				$lugarVC = LugarVerificacion::where($columna, 'LIKE', '%'.$valor.'%')->skip($pagina-1)->take($datos['limite'])->get();
				$total=$lugarVC;
			}
			else
			{
				$lugarVC = LugarVerificacion::skip($pagina-1)->take($datos['limite'])->get();
				$total=LugarVerificacion::all();
			}
			
		}
		else
		{
			$lugarVC = LugarVerificacion::all();
			$total=$lugarVC;
		}

		if(!$lugarVC)
		{
			return Response::json(array('status'=> 404,"messages"=>'No encontrado'),404);
		} 
		else 
		{
			return Response::json(array("status"=>200,"messages"=>"ok","data"=>$lugarVC,"total"=>count($total)),200);
			
		}
	}

	/**
	 * Store a newly created resource in storage.
	 *
	 * @return Response
	 */
	public function store(LugarVCRequest $request)
	{
		$datos = Input::json();
		$success = false;
        DB::beginTransaction();
        try 
		{
            $lugarVC = new LugarVerificacion;
            $lugarVC->nombre = $datos->get('nombre');

            if ($lugarVC->save()) 
                $success = true;
        } 
		catch (\Exception $e) 
		{
        }
        if ($success) 
		{
            DB::commit();
			return Response::json(array("status"=>201,"messages"=>"Creado","data"=>$lugarVC),201);
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
		$lugarVC = LugarVerificacion::find($id);

		if(!$lugarVC)
		{
			return Response::json(array('status'=> 404,"messages"=>'No encontrado'),404);
		} 
		else 
		{
			return Response::json(array("status"=>200,"messages"=>"ok","data"=>$lugarVC),200);
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
			$lugarVC = LugarVerificacion::find($id);
			$lugarVC->nombre = $datos->get('nombre');

            if ($lugarVC->save()) 
                $success = true;
		} 
		catch (\Exception $e) 
		{
        }
        if ($success)
		{
			DB::commit();
			return Response::json(array("status"=>200,"messages"=>"ok","data"=>$lugarVC),200);
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
			$lugarVC = LugarVerificacion::find($id);
			$lugarVC->delete();
			$success=true;
		} 
		catch (\Exception $e) 
		{
        }
        if ($success)
		{
			DB::commit();
			return Response::json(array("status"=>200,"messages"=>"ok","data"=>$lugarVC),200);
		} 
		else 
		{
			DB::rollback();
			return Response::json(array('status'=> 500,"messages"=>'Error interno del servidor'),500);
		}
	}

}
