@extends('layouts.default')

@section('title')
Profile of {{ $user->username }}
@stop

@section('scripts')
{{ HTML::script('js/jquery.flot.min.js') }}
{{ HTML::script('js/jquery.flot.pie.min.js') }}
@stop

@section('content')
<div class="col-lg-6 col-lg-offset-2">
    @if ($user->count())
    <?php
    	$phone = array();
    	if ($user->phone_institute !== '')
    		$phone = array_add($phone, 'Institute', $user->phone_institute);
    	if ($user->phone_mobile !== '')
    		$phone = array_add($phone, 'Mobile', $user->phone_mobile);
    	if ($user->phone_private !== '')
    		$phone = array_add($phone, 'Private', $user->phone_private);
    ?>
    <div class="page-header">
        <h2>Account of {{ $user->first_name." ".$user->last_name }}</h2>
    </div>
    <div>
      <table class="table table-striped table-hover">
        <tbody>
          <tr>
            <td>Username</td>
            <td>{{ $user->username }}</td>
          </tr>
          <tr>
            <td>Email</td>
            <td>{{ $user->email }}</td>
          </tr>
          <tr>
            <td>Workgroup</td>
            <td>{{ $user->workgroup->name }} [{{ $user->workgroup->country }}]</td>
          </tr>
          @if ($phone)
          <tr>
            <td>Phone</td>
            <td>{{ implode(', ', array_map(function ($v, $k) { return $k . ': ' . $v; }, $phone, array_keys($phone))) }}</td>
          </tr>
          @endif
          {{-- only show the following information to the belonging user or to the same workgrop PI's as well as admins --}}
          @if (Auth::id() == $user->id || Auth::user()->isAdmin() || (Auth::user()->isPI() && Auth::user()->workgroup_id == $user->workgroup_id))
          <tr>
            <td>Rating</td>
            <td>{{ $user->rating }}</td>
          </tr>
          <tr>
            <td>Total shifts</td>
            <td>
              {{ $user->shifts->count() }}&emsp;@if ($user->shifts->count()) (day: {{ $day = $user->shifts->sum(function($shift) { return $shift->is_day(); }) }}, late: {{ $late = $user->shifts->sum(function($shift) { return $shift->is_late(); }) }}, night: {{ $night = $user->shifts->sum(function($shift) { return $shift->is_night(); }) }})
              {{-- jQuery needs to be loaded before the other Javascript parts need it --}}
              {{ HTML::script('js/jquery-2.1.1.min.js') }}
              <script type="text/javascript">
              $(document).ready(function(){
                var data = [
                  {label: "day", data: {{{ $day }}}, color: "#8BC34A"},
                  {label: "late", data: {{{ $late }}}, color: "#FFA000"},
                  {label: "night", data: {{{ $night }}}, color: "#455A64"}
                ];

                var options = {
                  series: {
                    pie: {
                      show: true,
                      radius: 1,
                      label: {
                        show: true,
                        radius: 2/3,
                        // Add custom formatter
                        formatter: function(label, data) {
                          return '<div style="font-size: 14px; font-weight: bold; text-align: center; padding: 2px; color: white;">' + label + '<br/>' + Math.round(data.percent) + '%</div>';
                        },
                        threshold: 0.1
                      }
                    }
                  },
                  legend: {
                    show: false
                  },
                  grid: {
                    hoverable: true,
                    clickable: true
                  }
                };

                $.plot($("#flotcontainer"), data, options);
              });
              </script>
              <div id="flotcontainer" style="width: 300px; height: 250px; margin-top: 10px"></div>
              @endif
            </td>
          </tr>
          @endif
        </tbody>
      </table>
    </div>
    @else
        <h1>User {{ $user->username }} not found!</h1>
    @endif
</div>
@stop

