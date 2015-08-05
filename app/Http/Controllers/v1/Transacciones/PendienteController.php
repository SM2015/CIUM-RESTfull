<?php namespace App\Http\Controllers\v1\Transacciones;

use App\Http\Requests;
use App\Http\Controllers\Controller;

use Request;
use Response;
use Input;
use DB; 
use Sentry;
use App\Models\Transacciones\Pendiente;

class PendienteController extends Controller {

	/**
	 * Display a listing of the resource.
	 *
	 * @return Response
	 */
	public function index()
	{
		$datos = Request::all();
		$user = Sentry::getUser();
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
				$pendiente = Pendiente::select(array('id', 'nombre', 'descripcion', 'visto', 'recurso', 'parametro', 'idUsuario', 'creadoAl', 'modificadoAl', 'borradoAl',DB::raw('DATEDIFF(NOW(),creadoAl) as dias')))
				->where($columna, 'LIKE', '%'.$valor.'%')
				->where('idUsuario',$user->id)
				->skip($pagina-1)
				->take($datos->get('limite'))->get();
				$total=$pendiente;
			}
			else
			{
				$pendiente = Pendiente::select(array('id', 'nombre', 'descripcion', 'visto', 'recurso', 'parametro', 'idUsuario', 'creadoAl', 'modificadoAl', 'borradoAl',DB::raw('DATEDIFF(NOW(),creadoAl) as dias')))
				->where('idUsuario',$user->id)
				->skip($pagina-1)
				->take($datos['limite'])->get();
				$total=Pendiente::where('idUsuario',$user->id)->get();
			}
			
		}
		else if(array_key_exists('visto',$datos))
		{
			$pagina=$datos['pagina1'];
			if($pagina == 0)
			{
				$pagina = 1;
			}
			$pendiente = Pendiente::select(array('id', 'nombre', 'descripcion', 'visto', 'recurso', 'parametro', 'idUsuario', 'creadoAl', 'modificadoAl', 'borradoAl',DB::raw('DATEDIFF(NOW(),creadoAl) as dias')))
			->where('visto','!=','1')
			->where('idUsuario',$user->id)
			->skip($pagina-1)
			->take($datos['limite1'])->get();
			$total=Pendiente::where('idUsuario',$user->id)->where('visto','!=','1')->get();
		}
		else
		{
			$pendiente = Pendiente::where('idUsuario',$user->id)->get();
			$total=$pendiente;
		}

		if(!$pendiente)
		{
			return Response::json(array('status'=> 404,"messages"=>'No encontrado'),404);
		} 
		else 
		{
			return Response::json(array("status"=>200,"messages"=>"ok","data"=>$pendiente,"total"=>count($total)),200);
			
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
		$pendiente = Pendiente::where('idUsuario',$user->id)->where("id",$id)->first();
		$user = Sentry::getUser();
		if(!$pendiente)
		{
			return Response::json(array('status'=> 404,"messages"=>'No encontrado'),404);
		} 
		else 
		{
			return Response::json(array("status"=>200,"messages"=>"ok","data"=>$pendiente),200);
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
			$user = Sentry::getUser();
			$pendiente = Pendiente::find($id);
			if(array_key_exists('nombre',$datos))
			$pendiente->nombre = $datos->get('nombre');
		
			if(array_key_exists('descripcion',$datos))
			$pendiente->descripcion = $datos->get('descripcion');
		
			$pendiente->idUsuario = $user->id;
			
			if(array_key_exists('recurso',$datos))
			$pendiente->recurso = $datos->get('recurso');
		
			if(array_key_exists('parametro',$datos))
			$pendiente->parametro = $datos->get('parametro');
		
			$pendiente->visto = $datos->get('visto');			

            if ($pendiente->save()) 
                $success = true;
		} 
		catch (\Exception $e) 
		{
			throw $e;
        }
        if ($success)
		{
			DB::commit();
			return Response::json(array("status"=>200,"messages"=>"ok","data"=>$pendiente),200);
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
			$pendiente = Pendiente::find($id);
			$pendiente->delete();
			$success=true;
		} 
		catch (\Exception $e) 
		{
			throw $e;
        }
        if ($success)
		{
			DB::commit();
			return Response::json(array("status"=>200,"messages"=>"ok","data"=>$pendiente),200);
		} 
		else 
		{
			DB::rollback();
			return Response::json(array('status'=> 500,"messages"=>'Error interno del servidor'),500);
		}
	}

}
