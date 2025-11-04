<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contrat de Location - {{ $numeroContrat }}</title>
    <style>
        @page {
            margin: 2cm;
        }
        body {
            font-family: 'Times New Roman', serif;
            font-size: 12pt;
            line-height: 1.6;
            color: #000;
        }
        .header {
            text-align: left;
            margin-bottom: 20px;
        }
        .title {
            text-align: center;
            font-weight: bold;
            font-size: 14pt;
            margin: 30px 0;
            text-decoration: underline;
        }
        .section {
            margin: 20px 0;
        }
        .section-title {
            font-weight: bold;
            margin: 15px 0 10px 0;
        }
        .article {
            margin: 10px 0;
            text-align: justify;
        }
        .article-title {
            font-weight: bold;
            margin-bottom: 5px;
        }
        .signature-section {
            margin-top: 50px;
            display: flex;
            justify-content: space-between;
        }
        .signature-box {
            width: 45%;
            text-align: center;
        }
        .composition-item {
            margin-left: 20px;
            margin-bottom: 5px;
        }
        @media print {
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>
<div class="header">
    <div>Province de : <strong>{{ $province }}</strong></div>
    <div>Ville de : <strong>{{ $ville }}</strong></div>
    <div>Commune de : <strong>{{ $commune }}</strong></div>
    <div>Territoire de : <strong>{{ $territoire }}</strong></div>
    <div>Cité de : <strong>{{ $cite }}</strong></div>
</div>

<div class="title">
    CONTRAT DE LOCATION MODELE UNIQUE<br>
    N° {{ $numeroContrat }}
</div>

<div class="section">
    <p><strong>Entre les soussignés:</strong></p>

    <p>
        - Monsieur, Madame, Mademoiselle <strong>{{ $bailleurNom }}</strong><br>
        @if($bailleurRaisonSociale)
            (ou raison sociale) <strong>{{ $bailleurRaisonSociale }}</strong><br>
        @endif
        dénommé(e) "Bailleur(eresse)" résidant au n° <strong>{{ $bailleurAdresseNumero }}</strong><br>
        sur l'avenue (rue) <strong>{{ $bailleurRue }}</strong>, dans la ville de <strong>{{ $bailleurVille }}</strong>, commune ou<br>
        territoire de <strong>{{ $bailleurCommune }}</strong>, cité de <strong>{{ $bailleurCite }}</strong> d'une part;
    </p>

    <p style="margin-top: 15px;">et</p>

    <p>
        - Monsieur, Madame, Mademoiselle <strong>{{ $locataireNom }}</strong><br>
        @if($locataireRaisonSociale)
            (ou raison sociale) <strong>{{ $locataireRaisonSociale }}</strong><br>
        @endif
        dénommé(e) "Locataire" d'autre part.
    </p>

    <p style="margin-top: 15px;"><strong>Il est conclu ce qui suit :</strong></p>
</div>

<div class="section">
    <div class="section-title">I. Description du bien.</div>

    <div class="article">
        <div class="article-title">Article 1er :</div>
        <p>
            Le Bailleur donne en location au Locataire qui accepte, son bien immobilier
            situé au n° <strong>{{ $bienAdresseNumero }}</strong>, avenue (rue) <strong>{{ $bienRue }}</strong>,
            commune de (territoire) <strong>{{ $bienCommune }}</strong> cité de <strong>{{ $bienCite }}</strong>
            ville de <strong>{{ $bienVille }}</strong>, province de <strong>{{ $bienProvince }}</strong>
        </p>

        <p style="margin-top: 10px;">Ce bien immobilier se compose de :</p>
        @foreach($compositionBien as $item)
            <div class="composition-item">~ {{ $item }}</div>
        @endforeach
    </div>
</div>

<div class="section">
    <div class="section-title">II. Usage.</div>

    <div class="article">
        <div class="article-title">Article 2 :</div>
        <p>
            Le présent contrat s'applique au bien immobilier décrit-dessus mis en location pour
            usage: <strong>{{ $usage }}</strong>
        </p>
    </div>
</div>

<div class="section">
    <div class="section-title">III. Loyer.</div>

    <div class="article">
        <div class="article-title">Article 3 :</div>
        <p>Le loyer est mensuel. Il est fixé en monnaie nationale.</p>
        <p>
            Il est de <strong>{{ number_format($loyerChiffres, 0, ',', ' ') }} FCFA</strong> (en chiffres)<br>
            <strong>{{ $loyerLettres }}</strong> (en lettres)
        </p>
        <p style="margin-top: 10px;">Le taux de loyer ne peut être modifié qu'en cas de :</p>
        <p style="margin-left: 20px;">
            - plus-value du bien loué;<br>
            - réévaluation ou dévaluation officielle de la monnaie nationale.
        </p>
        <p>
            Cette modification doit faire l'objet d'un avenant au contrat contresigné par les deux parties et visé par
            l'Officier du Service Communal chargé de l'Habitat.
        </p>
    </div>
</div>

<div class="section">
    <div class="section-title">IV. Modalités de paiement.</div>

    <div class="article">
        <div class="article-title">Article 4 :</div>
        <p>
            Le paiement du loyer peut s'effectuer en espèces, par chèque certifié ou par virement bancaire
            {{ $modalitePaiement }}
        </p>
    </div>
</div>

<div class="section">
    <div class="section-title">V. Garantie.</div>

    <div class="article">
        <div class="article-title">Article 5 :</div>
        <p>La garantie locative est fixée à :</p>
        <p style="margin-left: 20px;">
            - trois mois de loyer, pour un bien immobilier à usage résidentiel ;<br>
            - six mois de loyer, pour un bien immobilier à usage commercial ;<br>
            - douze mois de loyer, pour un bien immobilier à usage industriel ou mixte.
        </p>
        <p style="margin-top: 10px;">
            <strong>Montant de la garantie : {{ number_format($garantie, 0, ',', ' ') }} FCFA</strong>
        </p>
    </div>

    <div class="article">
        <div class="article-title">Article 6 :</div>
        <p>
            A l'échéance du contrat de location, la garantie locative est remboursée au locataire après déduction, le
            cas échéant, des sommes dues au Bailleur.
        </p>
        <p>
            Au cours du bail, la garantie locative n'est pas réajustable et n'est pas productive d'intérêts
            quelconques. Elle ne peut servir aucunement au paiement des loyers au cours du bail, sauf accord
            exprès des deux Parties.
        </p>
    </div>
</div>

<div class="section">
    <div class="section-title">VI. Durée.</div>

    <div class="article">
        <div class="article-title">Article 7 :</div>
        <p>
            Pour garantir la stabilité du bail, le contrat est conclu pour une durée minimum d'un an prenant
            cours le <strong>{{ $dateDebut }}</strong> (date de réception par l'Officier du Service de l'Habitat).
            Il peut être renouvelé par tacite reconduction ou avec l'accord exprès des deux parties.
        </p>
        @if($renouvellementAutomatique)
            <p><strong>Renouvellement automatique : OUI</strong></p>
        @else
            <p><strong>Renouvellement automatique : NON</strong></p>
        @endif
        <p><strong>Date de fin du bail : {{ $dateFin }}</strong></p>
    </div>
</div>

<div class="section">
    <div class="section-title">VII. Obligations du Bailleur.</div>

    <div class="article">
        <div class="article-title">Article 8 :</div>
        <p>Le Bailleur est tenu aux obligations suivantes:</p>
        <p style="margin-left: 20px;">
            - mettre à la disposition du locataire le bien loué dans l'état approprié à sa destination;<br>
            - accorder une jouissance paisible du bien loué;<br>
            - s'acquitter de toutes les taxes légales en vigueur;<br>
            - payer sa quote-part des factures d'eau, d'électricité, du téléphone et/ou autres, pour autant qu'il en fasse usage.
        </p>
    </div>
</div>

<div class="section">
    <div class="section-title">VIII. Obligations du Locataire.</div>

    <div class="article">
        <div class="article-title">Article 9 :</div>
        <p>Le Locataire est tenu aux obligations ci-après :</p>
        <p style="margin-left: 20px;">
            - payer régulièrement son loyer aux termes convenus;<br>
            - user du bien loué en bon père de famille;<br>
            - répondre des dégradations du bien loué qui surviendraient pendant le bail et pour lesquelles il serait responsable;<br>
            - payer régulièrement sa facture ou quote-part de facture de consommation d'eau, d'électricité, du téléphone etc.<br>
            - ne pas apporter des modifications quelconques au bien loué sans l'accord écrit du Bailleur.
        </p>
    </div>
</div>

<div class="section">
    <div class="section-title">IX. Sous-location ou cession.</div>

    <div class="article">
        <div class="article-title">Article 10 :</div>
        <p>
            Il est interdit au locataire de sous-louer tout partie du bien loué comme de céder tout ou partie de son
            droit de bail.
        </p>
    </div>
</div>

<div class="section">
    <div class="section-title">X. Conditions de résiliation.</div>

    <div class="article">
        <div class="article-title">Article 11 :</div>
        <p>Le contrat de location prend fin, soit:</p>
        <p style="margin-left: 20px;">
            1° à l'expiration du terme convenu et/ou non renouvelé;<br>
            2° sur accord des deux parties;<br>
            3° à l'initiative de l'une des parties suite à l'inexécution par l'autre de ses obligations;<br>
            4° par la perte du bien loué dû à un désastre naturel.
        </p>
    </div>

    <div class="article">
        <div class="article-title">Article 12 :</div>
        <p>
            En cas d'aliénation de l'immeuble, le Bailleur doit en informer le locataire et lui accorder un préavis légal.
        </p>
    </div>

    <div class="article">
        <div class="article-title">Article 13 :</div>
        <p>
            En cas de décès d'une des parties, le contrat prend fin à l'échéance convenue à l'article 7 et ne peut
            être renouvelé par tacite reconduction.
        </p>
    </div>

    <div class="article">
        <div class="article-title">Article 14 :</div>
        <p>Le préavis légal correspond au nombre des mois de garanties locatives.</p>
    </div>
</div>

<div class="section">
    <div class="section-title">XI. Instance d'arbitrage.</div>

    <div class="article">
        <div class="article-title">Article 15 :</div>
        <p>
            A défaut de règlement à l'amiable, tout conflit éventuel est soumis au Service local de l'Habitat à priori.
        </p>
    </div>
</div>

<div class="section">
    <div class="section-title">XII. Sanction.</div>

    <div class="article">
        <div class="article-title">Article 16 :</div>
        <p>
            La non-légalisation de contrat de location dans un délai de 72 heures après sa signature, entraîne le
            paiement par les parties d'une amende équivalent à un mois de loyer.
        </p>
    </div>
</div>

<div style="margin-top: 40px;">
    <p>Fait à <strong>{{ $villeFait ?? 'Kinshasa' }}</strong>, le <strong>{{ $dateSignature }}</strong></p>
</div>

<div class="signature-section">
    <div class="signature-box">
        <p><strong>LE BAILLEUR</strong></p>
        <p style="margin-top: 60px;">{{ $bailleurNom }}</p>
        <p>(Nom et Signature)</p>
    </div>

    <div class="signature-box">
        <p><strong>LE LOCATAIRE</strong></p>
        <p style="margin-top: 60px;">{{ $locataireNom }}</p>
        <p>(Nom et Signature)</p>
    </div>
</div>

<div class="signature-section" style="margin-top: 40px;">
    <div class="signature-box">
        <p><strong>LE SERVICE DE L'HABITAT</strong></p>
        <p style="margin-top: 60px;">_____________________</p>
    </div>

    <div class="signature-box">
        <p><strong>L'AUTORITE ADMINISTRATIVE LOCALE</strong></p>
        <p style="margin-top: 60px;">_____________________</p>
    </div>
</div>

<div style="margin-top: 30px; text-align: center;">
    <p><strong>DONT COUT</strong></p>
    <p>FC ………………………</p>
    <p>TIMBRES FISCAUX</p>
</div>

<div class="no-print" style="text-align: center; margin-top: 40px;">
    <button onclick="window.print()" style="background: #2563eb; color: white; padding: 12px 24px; border: none; border-radius: 6px; cursor: pointer; font-size: 16px;">
        Imprimer le contrat
    </button>
</div>
</body>
</html>
