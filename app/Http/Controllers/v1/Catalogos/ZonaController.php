<?php namespace App\Http\Controllers\v1\Catalogos;

use App\Http\Requests;
use App\Http\Controllers\Controller;

use Request;
use Response;
use Input;
use DB;
use App\Models\Catalogos\Zona;
use App\Http\Requests\ZonaRequest;


class ZonaController extends Controller {

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
				$zona = Zona::where($columna, 'LIKE', '%'.$valor.'%')->skip($pagina-1)->take($datos['limite'])->orderBy($order,$orden)->get();
				$total=$zona;
			}
			else
			{
				$zona = Zona::skip($pagina-1)->take($datos['limite'])->orderBy($order,$orden)->get();
				$total=Zona::all();
			}
			
		}
		else
		{
			if(array_key_exists('jurisdiccion',$datos))
			{
				$zona = DB::table('ZonaClues AS u')
					->leftJoin('Zona AS z', 'z.id', '=', 'u.idZona')
					->distinct()
					->select(array("z.id","z.nombre"))
					->where('u.jurisdiccion',$datos["jurisdiccion"])->get();
			}
			else
				$zona = Zona::all();
			$total=$zona;
		}
		
		if(!$zona)
		{
			return Response::json(array('status'=> 404,"messages"=>'No encontrado'),404);
		} 
		else 
		{
			return Response::json(array("status"=>200,"messages"=>"ok","data"=>$zona,"total"=>count($total)),200);
		}
	}

	/**
	 * Store a newly created resource in storage.
	 *
	 * @return Response
	 */
	public function store()
	{
		$rules = [
			'nombre' => 'required|min:3|max:150',
			'usuarioclues' =>  'array'
		];
		$v = \Validator::make(Request::json()->all(), $rules );

		if ($v->fails())
		{
			return Response::json($v->errors());
		}
		$datos = Input::json();
		$success = false;
        DB::beginTransaction();
        try 
		{
            $zona = new Zona;
            $zona->nombre = $datos->get('nombre');

            if ($zona->save()) 
			{
				DB::table('ZonaClues')->where('idZona', "$zona->id")->delete();
				
				foreach($datos->get('usuarioclues') as $clues)
				{
					if($clues)								
						DB::table('ZonaClues')->insert(	array('idZona' => "$zona->id", 'clues' => $clues['clues'], 'jurisdiccion' => $clues['jurisdiccion']) );					
				}		
				if(array_key_exists('all',$datos))
					if($datos->get('all'))
						DB::table('ZonaClues')->insert(	array('idZona' => "$zona->id", 'clues' => $datos->get('all')) );
					
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
			return Response::json(array("status"=>201,"messages"=>"Creado","data"=>$zona),201);
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
		$zona = Zona::find($id);

		if(!$zona)
		{
			return Response::json(array('status'=> 404,"messages"=>'No encontrado'),404);
		} 
		else 
		{
			$zona['usuarioclues'] = DB::table('ZonaClues AS u')
			->leftJoin('Clues AS c', 'c.clues', '=', 'u.clues')
			->select('*')
			->where('idZona',$id)->get();
			return Response::json(array("status"=>200,"messages"=>"ok","data"=>$zona),200);
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
		$rules = [
			'nombre' => 'required|min:3|max:150',
			'usuarioclues' =>  'array'
		];
		$v = \Validator::make(Request::json()->all(), $rules );

		if ($v->fails())
		{
			return Response::json($v->errors());
		}
		$datos = Input::json(); 
		$success = false;
        DB::beginTransaction();
        try 
		{
			$zona = Zona::find($id);
			$zona->nombre = $datos->get('nombre');

            if ($zona->save())
			{
				DB::table('ZonaClues')->where('idZona', "$zona->id")->delete();
				
				foreach($datos->get('usuarioclues') as $clues)
				{
					if($clues)								
						DB::table('ZonaClues')->insert(	array('idZona' => "$zona->id", 'clues' => $clues['clues'], 'jurisdiccion' => $clues['jurisdiccion']) );					
				}	
				if(array_key_exists('all',$datos))
					if($datos->get('all'))
						DB::table('ZonaClues')->insert(	array('idZona' => "$zona->id", 'clues' => $datos->get('all')) ); 
                
				$success = true;
			}
		} 
		catch (\Exception $e) 
		{throw $e;
        }
        if ($success)
		{
			DB::commit();
			return Response::json(array("status"=>200,"messages"=>"ok","data"=>$zona),200);
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
			$zona = Zona::find($id);
			$zona->delete();
			$success=true;
		} 
		catch (\Exception $e) 
		{
			throw $e;
        }
        if ($success)
		{
			DB::commit();
			return Response::json(array("status"=>200,"messages"=>"ok","data"=>$zona),200);
		} 
		else 
		{
			DB::rollback();
			return Response::json(array('status'=> 500,"messages"=>'Error interno del servidor'),500);
		}
	}

}
