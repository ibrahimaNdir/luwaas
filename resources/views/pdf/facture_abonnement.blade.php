<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: sans-serif; }
        .header { text-align: center; margin-bottom: 30px; }
        .details { margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Facture Luwaas</h1>
        <p>Facture N°: {{ $subscription->transaction_ref ?? 'N/A' }}</p>
    </div>
    
    <div class="details">
        <p><strong>Bailleur :</strong> {{ $subscription->proprietaire->user->nom }} {{ $subscription->proprietaire->user->prenom }}</p>
        <p><strong>Date :</strong> {{ now()->format('d/m/Y') }}</p>
    </div>

    <table>
        <tr>
            <th>Description</th>
            <th>Montant</th>
        </tr>
        <tr>
            <td>Abonnement {{ $subscription->plan->name }} ({{ $subscription->plan->billing_cycle }})</td>
            <td>{{ number_format($subscription->amount, 0, ',', ' ') }} FCFA</td>
        </tr>
        <tr>
            <td><strong>Total</strong></td>
            <td><strong>{{ number_format($subscription->amount, 0, ',', ' ') }} FCFA</strong></td>
        </tr>
    </table>
</body>
</html>
