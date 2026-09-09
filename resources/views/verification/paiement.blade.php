<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vérification de paiement — Luwaas</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: #f4f6f9;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
            color: #1a202c;
        }

        .card {
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.10);
            max-width: 480px;
            width: 100%;
            overflow: hidden;
        }

        /* ── Header bande colorée ── */
        .card-header {
            padding: 32px 32px 24px;
            text-align: center;
        }
        .card-header.valid   { background: linear-gradient(135deg, #1a7a4a 0%, #2ecc71 100%); }
        .card-header.invalid { background: linear-gradient(135deg, #c0392b 0%, #e74c3c 100%); }

        .status-icon {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            background: rgba(255,255,255,0.25);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            font-size: 36px;
        }

        .card-header h1 {
            font-size: 22px;
            font-weight: 700;
            color: #fff;
            margin-bottom: 6px;
        }

        .card-header p {
            font-size: 14px;
            color: rgba(255,255,255,0.85);
        }

        /* ── Corps ── */
        .card-body {
            padding: 28px 32px 32px;
        }

        .badge-certifie {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 12px;
            border-radius: 999px;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 24px;
        }
        .badge-certifie.online  { background: #e6f9ee; color: #1a7a4a; border: 1px solid #a3e4b8; }
        .badge-certifie.manuel  { background: #fff8e6; color: #7a5a1a; border: 1px solid #f0d8a3; }

        .details-grid {
            display: grid;
            gap: 0;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            overflow: hidden;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 14px 18px;
            border-bottom: 1px solid #e2e8f0;
        }
        .detail-row:last-child { border-bottom: none; }

        .detail-label {
            font-size: 13px;
            color: #718096;
            font-weight: 500;
        }

        .detail-value {
            font-size: 14px;
            font-weight: 600;
            color: #1a202c;
            text-align: right;
        }

        .detail-value.amount {
            font-size: 18px;
            color: #1a7a4a;
        }

        /* ── Notice ── */
        .notice {
            margin-top: 20px;
            padding: 12px 16px;
            background: #f7faff;
            border-left: 3px solid #4a90d9;
            border-radius: 0 8px 8px 0;
            font-size: 12px;
            color: #4a5568;
            line-height: 1.6;
        }

        /* ── Footer ── */
        .card-footer {
            padding: 16px 32px;
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
            text-align: center;
        }

        .card-footer p {
            font-size: 12px;
            color: #a0aec0;
        }

        .card-footer strong {
            color: #4a5568;
        }

        /* ── Introuvable ── */
        .not-found-body {
            padding: 28px 32px 32px;
            text-align: center;
        }

        .not-found-body p {
            font-size: 15px;
            color: #4a5568;
            line-height: 1.7;
            margin-bottom: 16px;
        }

        .token-display {
            font-family: monospace;
            font-size: 11px;
            color: #a0aec0;
            background: #f7faff;
            padding: 8px 12px;
            border-radius: 6px;
            word-break: break-all;
        }
    </style>
</head>
<body>

@if($valide)

<div class="card">
    <div class="card-header valid">
        <div class="status-icon">✅</div>
        <h1>Paiement vérifié</h1>
        <p>Ce document est authentique et enregistré sur Luwaas</p>
    </div>

    <div class="card-body">

        <div class="badge-certifie {{ $est_certifie ? 'online' : 'manuel' }}">
            @if($est_certifie)
                🔒 Certifié Luwaas — paiement en ligne
            @else
                📋 Déclaration manuelle du bailleur
            @endif
        </div>

        <div class="details-grid">
            <div class="detail-row">
                <span class="detail-label">Locataire</span>
                <span class="detail-value">{{ $nom_locataire }}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Montant payé</span>
                <span class="detail-value amount">{{ $montant }} FCFA</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Période couverte</span>
                <span class="detail-value">{{ $periode }}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Date de paiement</span>
                <span class="detail-value">{{ $date_paiement }}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Mode</span>
                <span class="detail-value">{{ $type_paiement }}</span>
            </div>
        </div>

        <div class="notice">
            Ce document a été vérifié en temps réel sur la plateforme Luwaas.
            @if($est_certifie)
                Le paiement en ligne garantit la traçabilité complète de cette transaction.
            @else
                Ce paiement a été déclaré manuellement par le bailleur et n'est pas associé à une transaction électronique.
            @endif
        </div>
    </div>

    <div class="card-footer">
        <p>Vérifié via <strong>Luwaas</strong> — Plateforme de gestion locative</p>
        <p style="margin-top:4px;">{{ now()->format('d/m/Y à H:i') }}</p>
    </div>
</div>

@else

<div class="card">
    <div class="card-header invalid">
        <div class="status-icon">❌</div>
        <h1>Document introuvable</h1>
        <p>Ce QR code ne correspond à aucun paiement enregistré</p>
    </div>

    <div class="not-found-body">
        <p>
            Aucun paiement vérifié n'a été trouvé pour ce code.
            Il est possible que ce document soit <strong>falsifié</strong>,
            périmé, ou que le QR code soit illisible.
        </p>
        <p>En cas de doute, contactez directement la plateforme Luwaas.</p>
        <div class="token-display">Référence : {{ $token }}</div>
    </div>

    <div class="card-footer">
        <p>Vérification effectuée via <strong>Luwaas</strong> — {{ now()->format('d/m/Y à H:i') }}</p>
    </div>
</div>

@endif

</body>
</html>
