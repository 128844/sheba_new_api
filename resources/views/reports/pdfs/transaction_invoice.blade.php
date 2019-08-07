<!DOCTYPE html>
<html lang="en">
<head>
    <title> Invoice </title>
    <style>
        .invoice-table tr {
            color: #689ab8;
            font-size: 14px;
        }

        .invoice-table tr img {

        }

        .terms {
            font-size: 12px;
            padding-top: 20px;
            border-top: 1px solid #e1e1e1;
            color: #4a4a4a;
        }
    </style>
</head>

<body>
<table align="center" style="max-width: 800px;margin: auto;min-width: 600px;font-family: Arial, sans-serif">
    <tbody>
    <tr style="border-bottom: 1px solid #ddd">
        <td>
            <table style="width: 100%;border-bottom: 1px solid #ddd">
                @if(isset($payment_receiver))
                    <tr>
                        <td width="120"><img style="max-width: 120px" src="{{$payment_receiver['image']}}" alt=""></td>
                        <td colspan="3">
                            <div style="text-align: left;padding: 10px 20px;">
                                <span>{{$payment_receiver['name']}}</span><br>
                                <span style="color: #B0BEC5;">{{$payment_receiver['mobile']}}</span><br>
                                <span style="color: #B0BEC5;">{{$payment_receiver['address']}}</span>
                            </div>
                        </td>
                    </tr>
                @endif
            </table>
        </td>
    </tr>
    <tr>
        <td>

            <table style="width: 100%;border-bottom: 1px solid #ddd;padding-bottom: 4px">
                <tr>
                    <td colspan="3" style="border-left: 4px solid #1b4280">
                        @if(isset($user))
                            <div style="padding: 10px 20px">
                                <span style="color: #B0BEC5;">Bill to</span><br>
                                <span style="font-weight: bold;">{{$user['name']}}</span><br>
                                <span style="color: #B0BEC5">{{$user['mobile']}}</span>
                            </div>
                        @endif
                    </td>
                    <td align="right">
                        <div style="text-align: right">
                            <span style="font-weight: bold"> <span style="font-family:Helvetica, sans-serif;">BDT</span> {{number_format($amount,2)}}</span><br>
                            <span style="color: #B0BEC5;">{{$created_at}}</span>
                        </div>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
    @if(isset($pos_order))
        <tr>
            <td>
                <table style="border-bottom: 1px solid #ddd;width: 100%;color: #929292" cellspacing="5">
                    <thead>
                    <tr>
                        <th style="color: #9b9b9b">Product Name</th>
                        <th style="color: #9b9b9b">Quantity</th>
                        <th style="color: #9b9b9b">Unit Price</th>
                        <th style="color: #9b9b9b" align="right">Total Price</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php $subtotal = 0;?>
                    @foreach($pos_order['items'] as $item)
                        <tr>
                            <td style="color: #383d46">{{$item->service_name}}</td>
                            <td style="color: #383d46">{{$item->quantity}}</td>
                            <td style="color: #383d46">BDT {{number_format($item->unit_price,2)}}</td>
                            <?php $total = (double)$item->quantity * (double)$item->unit_price; $subtotal += $total;?>
                            <td align="right" style="color: #383d46">BDT {{number_format($total,2)}}</td>
                        </tr>
                    @endforeach
                    <tr>
                        <td  style="border-top-color: #9b9d9b;border-top-width: 1px ;border-top-style: solid;mso-border-top-width-alt: 1px" bordercolor="#9b9d9b" colspan="3" align="right">Total</td>
                        <td  style="border-top-color: #9b9d9b;border-top-width: 1px ;border-top-style: solid;mso-border-top-width-alt: 1px" bordercolor="#9b9d9b" align="right" style="color: #9b9b9b">BDT {{number_format($pos_order['total'])}}</td>
                    </tr>
                    <tr>
                        <td colspan="3" align="right">Total Vat</td>
                        <td align="right" style="color: #9b9b9b">BDT {{number_format($pos_order['vat'],2)}}</td>
                    </tr>
                    <tr>
                        <td colspan="3" align="right">Total Discount</td>
                        <td align="right" style="color: #9b9b9b">BDT {{number_format($pos_order['discount'],2)}}</td>
                    </tr>
                    <tr>
                        <td colspan="3" align="right">Total Payable</td>
                        <td align="right" style="color: #9b9b9b">BDT {{number_format($pos_order['grand_total'],2)}}</td>
                    </tr>
                    <tr>
                        <td colspan="3" align="right">Paid</td>
                        <td align="right" style="color: #9b9b9b">BDT {{number_format($pos_order['paid'],2)}}</td>
                    </tr>
                    <tr>
                        <td colspan="3" align="right">Due</td>
                        <td align="right" style="color: #9b9b9b">BDT {{number_format($pos_order['due'],2)}}</td>
                    </tr>
                    </tbody>
                </table>
            </td>
        </tr>
    @endif
    <tr>
        <td>
            <table style="width: 100%;color: #383d46;" class="invoice-table" cellpadding="5">
                <tr>
                    <td>
                        <img src="https://cdn-shebaxyz.s3.ap-south-1.amazonaws.com/icons/bag.png"/>
                    </td>
                    <td style="color: #383d46;">Payment amount</td>
                    <td style="color: #383d46;" align="right">BDT {{number_format($amount,2)}}</td>
                </tr>
                <tr>
                    <td><img src="https://cdn-shebaxyz.s3.ap-south-1.amazonaws.com/icons/info.png"/></td>
                    <td style="color: #383d46;">Payment purpose</td>
                    <td style="color: #383d46;" align="right">{{$description}}</td>
                </tr>
                <tr>
                    <td><img src="https://cdn-shebaxyz.s3.ap-south-1.amazonaws.com/icons/check.png"/></td>
                    <td style="color: #383d46;">Payment time</td>
                    <td style="color: #383d46;" align="right">{{$created_at}}</td>
                </tr>
                <tr>
                    <td><img src="https://cdn-shebaxyz.s3.ap-south-1.amazonaws.com/icons/credit.png"/></td>
                    <td style="color: #383d46;">Payment type</td>
                    <td style="color: #383d46;" align="right">{{$method}}</td>
                </tr>
            </table>
        </td>
    </tr>
    <tr>
        <td>
            <p class="terms"><strong>Terms and Note:</strong> If needed, it can take a maximum of 15 days to get your
                refund. You will only get return by the way you pay.</p>
        </td>
    </tr>
    </tbody>
</table>

</body>
</html>
