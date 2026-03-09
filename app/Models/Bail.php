<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Bail extends Model
{
    protected $table = 'baux';

    protected $fillable = [
        // Relations
        'demande_id',
        'logement_id',
        'locataire_id',

        // Finances
        'montant_loyer',
        'charges_mensuelles',
        'nombre_mois_caution',
        'montant_caution_total',

        // Dates & Échéances
        'date_debut',
        'date_fin',
        'jour_echeance',
        'renouvellement_automatique',

        // Statut
        'statut',
        'date_activation',

        // Documents
        'document_pdf_path',
        'document_scan_path',

        // Conditions
        'conditions_speciales',
    ];

    protected $casts = [
        'renouvellement_automatique' => 'boolean',
        'date_debut'                 => 'date',
        'date_fin'                   => 'date',
        'date_activation'            => 'datetime',
    ];

    // ═══════════════════════════════════════════
    // RELATIONS
    // ═══════════════════════════════════════════

    public function logement()
    {
        return $this->belongsTo(Logement::class);
    }

    public function locataire()
    {
        return $this->belongsTo(Locataire::class);
    }

    public function demande()
    {
        return $this->belongsTo(Demande::class);
    }

    public function paiements()
    {
        return $this->hasMany(Paiement::class);
    }

    // ═══════════════════════════════════════════
    // ACCESSEURS
    // ═══════════════════════════════════════════

    public function getStatutDynamiqueAttribute(): string
    {
        $today = today();

        if (in_array($this->statut, ['resilie', 'suspendu'])) {
            return $this->statut;
        }

        if ($this->statut === 'en_attente_paiement') {
            return 'en_attente_paiement';
        }

        if ($today->lt($this->date_debut)) {
            return 'en_attente_paiement';
        }

        if ($today->gte($this->date_debut) && $today->lte($this->date_fin)) {
            return 'actif';
        }

        if ($today->gt($this->date_fin)) {
            return 'expire';
        }

        return $this->statut;
    }
}
