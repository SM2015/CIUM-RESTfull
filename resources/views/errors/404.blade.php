@extends('errors.layout')

@section('title-page') 404 @stop

@section('js')
@parent
@stop


@section('aside')
@stop

@section('content')
<div class="row">
	<div class="col-md-12">
		<div class="panel panel-default datagrid">
		    <div class="panel-heading"> <h1><span class="glyphicon glyphicon-warning-sign"></span> 404</h1></div>
	        <div class="panel-body">
				<h2>Oops, parece que te has perdido.</h2>
				<p>Lo sentimos no podemos ayudarte. La información que has solicitado no existe, esto se debe a que has escrito mal la URL en tu navegador o quizás la información desapareció mágicamente.</p>
		    </div> 
		</div>
	</div>
</div> 	

@stop
@parent
@stop
	
