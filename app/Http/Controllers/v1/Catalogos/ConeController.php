<?php namespace App\Http\Controllers\v1\Catalogos;

use App\Http\Requests;
use App\Http\Controllers\Controller;

use Request;
use Response;
use Input;
use DB;
use App\Models\Catalogos\Cone;
use App\Http\Requests\ConeRequest;


class ConeController extends Controller {

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
				$cone = Cone::where($columna, 'LIKE', '%'.$valor.'%')->skip($pagina-1)->take($datos['limite'])->orderBy($order,$orden)->get();
				$total=$cone;
			}
			else
			{
				$cone = Cone::skip($pagina-1)->take($datos['limite'])->orderBy($order,$orden)->get();
				$total=Cone::all();
			}
			
		}
		else
		{
			$cone = Cone::all();
			$total=$cone;
		}
		
		if(!$cone)
		{
			return Response::json(array('status'=> 404,"messages"=>'No encontrado'),404);
		} 
		else 
		{
			return Response::json(array("status"=>200,"messages"=>"ok","data"=>$cone,"total"=>count($total)),200);
		}
	}

	/**
	 * Store a newly created resource in storage.
	 *
	 * @return Response
	 */
	public function store(ConeRequest $request)
	{
		$datos = Input::json();
		$success = false;
        DB::beginTransaction();
        try 
		{
            $cone = new Cone;
            $cone->nombre = $datos->get('nombre');

            if ($cone->save()) 
			{
				DB::table('ConeClues')->where('idCone', "$cone->id")->delete();
				
				foreach($datos->get('usuarioclues') as $clues)
				{
					if($clues)								
						DB::table('ConeClues')->insert(	array('idCone' => "$cone->id", 'clues' => $clues['clues']) );					
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
			return Response::json(array("status"=>201,"messages"=>"Creado","data"=>$cone),201);
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
		$cone = Cone::find($id);

		if(!$cone)
		{
			return Response::json(array('status'=> 404,"messages"=>'No encontrado'),404);
		} 
		else 
		{
			$cone["usuarioclues"] = DB::table('ConeClues AS u')
			->leftJoin('Clues AS c', 'c.clues', '=', 'u.clues')
			->select(array('u.clues','c.nombre','c.jurisdiccion','c.municipio','c.localidad'))
			->where('idCone',$id)->get();
			return Response::json(array("status"=>200,"messages"=>"ok","data"=>$cone),200);
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
			$cone = Cone::find($id);
			$cone->nombre = $datos->get('nombre');

            if ($cone->save()) 
			{
				DB::table('ConeClues')->where('idCone', "$cone->id")->delete();
				
				foreach($datos->get('usuarioclues') as $clues)
				{
					if($clues)								
						DB::table('ConeClues')->insert(	array('idCone' => "$cone->id", 'clues' => $clues['clues']) );					
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
			return Response::json(array("status"=>200,"messages"=>"ok","data"=>$cone),200);
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
			$cone = Cone::find($id);
			$cone->delete();
			$success=true;
		} 
		catch (\Exception $e) 
		{
			throw $e;
        }
        if ($success)
		{
			DB::commit();
			return Response::json(array("status"=>200,"messages"=>"ok","data"=>$cone),200);
		} 
		else 
		{
			DB::rollback();
			return Response::json(array('status'=> 500,"messages"=>'Error interno del servidor'),500);
		}
	}

}
