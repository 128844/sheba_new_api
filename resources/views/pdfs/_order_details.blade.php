<table class="order-info" style="border-bottom:1px solid #666;">
    <tr>
        <td style="width:140px;">Order Number</td>
        <td style="width:200px;">: {{ $partner_order->code() }}</td>
        <td> </td>
        <td style="width:140px; text-align:left;">Customer Name</td>
        <td style="width:200px;">:  {{ $partner_order->order->delivery_name }}</td>
    </tr>
    <tr>
        <td style="width:140px;"> Order Date</td>
        <td>: {{ $partner_order->order->created_at->format('d M, Y h:i A') }}</td>
        <td> </td>
        <td style="width:140px; text-align:left;">Customer Phone</td>
        <td style="width:200px;">:  {{ $partner_order->order->delivery_mobile }}</td>
    </tr>
    <tr>
        <td style="width:140px;">SP Order Statement No</td>
        <td style="width:200px;">: {{ $partner_order->id }}</td>
        <td> </td>
        <td style="width:140px; text-align:left;">Customer Address</td>
        <td style="width:200px;">:  {{ empty($partner_order->order->deliveryAddress) ? 'N/A': ($partner_order->order->deliveryAddress->address ?: 'N/S') }}</td>
    </tr>
    <tr>
        <td> Statement Date</td>
        <td>: {{ \Carbon\Carbon::now()->format('d M, Y h:i A') }}</td>
        <td> </td>
        <td style="width:140px; text-align:left;">Service Provider Name</td>
        <td class="bangla-font-invoice" style="width:200px; text-align:left;">: {{ $partner_order->partner ? $partner_order->partner->name : 'Service provider not assigned' }}</td>

    </tr>
</table>
<br>

{{--

<table style="border-bottom:1px solid #666;">
    <tr>
        <td style="width:130px;">Client Name</td>
        <td>:  {{ $partner_order->order->delivery_name }}</td>
    </tr>
    <tr>
        <td>Mobile</td>
        <td>:  {{ $partner_order->order->delivery_mobile }}</td>
    </tr>
    <tr>
        <td>Address</td>
        <td>:  {{ $partner_order->order->delivery_address }}</td>
    </tr>

</table>
<br>

--}}
