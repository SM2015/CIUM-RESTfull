<?php namespace App\Http\Controllers\v1\Catalogos;

use App\Http\Requests;
use App\Http\Controllers\Controller;

use Request;
use Response;
use Input;
use DB; 
use App\Models\Catalogos\Alerta;
use App\Http\Requests\AlertaRequest;


class AlertaController extends Controller {

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
				$alerta = Alerta::where($columna, 'LIKE', '%'.$valor.'%')->skip($pagina-1)->take($datos['limite'])->get();
				$total=$alerta;
			}
			else
			{
				$alerta = Alerta::skip($pagina-1)->take($datos['limite'])->get();
				$total=Alerta::all();
			}
			
		}
		else
		{
			$alerta = Alerta::all();
			$total=$alerta;
		}

		if(!$alerta)
		{
			return Response::json(array('status'=> 404,"messages"=>'No encontrado'),404);
		} 
		else 
		{
			return Response::json(array("status"=>200,"messages"=>"ok","data"=>$alerta,"total"=>count($total)),200);
			
		}
	}

	/**
	 * Store a newly created resource in storage.
	 *
	 * @return Response
	 */
	public function store(AlertaRequest $request)
	{
		$datos = Input::json();
		$success = false;
		
        DB::beginTransaction();
        try 
		{
			$color = $datos->get('color');		
			if(count(explode(",",$color))<4)
				$color = "hsla".substr($datos->get('color'),3,strlen($datos->get('color'))-4)." 0.62)";
			
            $alerta = new Alerta;
            $alerta->nombre = $datos->get('nombre');
			$alerta->color = $color;

            if ($alerta->save()) 
                $success = true;
        } 
		catch (\Exception $e) 
		{
			
        }
        if ($success) 
		{
            DB::commit();
			return Response::json(array("status"=>201,"messages"=>"Creado","data"=>$alerta),201);
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
		$alerta = Alerta::find($id);

		if(!$alerta)
		{
			return Response::json(array('status'=> 404,"messages"=>'No encontrado'),404);
		} 
		else 
		{
			return Response::json(array("status"=>200,"messages"=>"ok","data"=>$alerta),200);
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
			
			$alerta = Alerta::find($id);
			$alerta->nombre = $datos->get('nombre');
			$alerta->color = $color;

            if ($alerta->save()) 
                $success = true;
		} 
		catch (\Exception $e) 
		{
        }
        if ($success)
		{
			DB::commit();
			return Response::json(array("status"=>200,"messages"=>"ok","data"=>$alerta),200);
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
			$alerta = Alerta::find($id);
			$alerta->delete();
			$success=true;
		} 
		catch (\Exception $e) 
		{
        }
        if ($success)
		{
			DB::commit();
			return Response::json(array("status"=>200,"messages"=>"ok","data"=>$alerta),200);
		} 
		else 
		{
			DB::rollback();
			return Response::json(array('status'=> 500,"messages"=>'Error interno del servidor'),500);
		}
	}

}
