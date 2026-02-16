@extends('layouts.app')

@section('content')
<div class="container">
	<h2>Topups {{ $date->format('d-m-Y') }}</h2>

	<h3>Totals</h3>
	<table class="table">
	@foreach($totals as $type => $total)
		<tbody>
			<tr>
				<td>{{ $type }}</td>
				<td>{{ number_format($total / 100, 2) }}</td>
			</tr>
		</tbody>
	@endforeach
	</table>

	<h3>Transactions</h3>
	<table class="table">

		<thead>
			<tr>
				<th>Date</th>
				<th>Value</th>
				<th>Card UID</th>
				<th>Topup type</th>
				<th>Reason</th>
			</tr>
		</thead>

	@foreach($transactions as $transaction)
		<tbody>
			<tr>
				<td>{{ $transaction->created_at->format('d-m-Y H:i:s') }}</td>
				<td>{{ number_format($transaction->value / 100, 2) }}</td>
				<td>{{ $transaction->card->uid }}</td>
				<td>{{ $transaction->getTypeDescription() }}</td>
				<td>{{ $transaction->topup ? $transaction->topup->reason : '' }}</td>
			</tr>
		</tbody>
	@endforeach
	</table>

</div>
@endsection
