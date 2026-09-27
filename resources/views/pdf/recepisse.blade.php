<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Récépissé {{ $signalement->recepisse }}</title>
  <style>
    @page { margin: 28px 36px; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1e2a22; }
    .bandeau { width: 100%; border-bottom: 3px solid #0f7a43; padding-bottom: 10px; }
    .bandeau td { vertical-align: middle; }
    .armoiries { width: 58px; }
    .entete-titre { font-size: 13px; font-weight: bold; color: #0d5f35; }
    .entete-sous { font-size: 9px; color: #5e6f65; text-transform: uppercase; letter-spacing: 1px; }
    .drapeau { height: 5px; margin-top: 4px; }
    .drapeau span { display: inline-block; width: 49%; height: 5px; }
    h1 { font-size: 18px; text-align: center; margin: 22px 0 4px; color: #0d5f35; letter-spacing: 1px; }
    .numero-box { margin: 10px auto 18px; width: 300px; border: 2px dashed #0f7a43; background: #edf8f1; text-align: center; padding: 10px; }
    .numero-label { font-size: 9px; text-transform: uppercase; color: #5e6f65; letter-spacing: 1px; }
    .numero { font-size: 22px; font-weight: bold; color: #0d5f35; letter-spacing: 2px; }
    .date { text-align: center; color: #5e6f65; margin-bottom: 16px; }
    h2 { font-size: 12px; background: #0f7a43; color: #ffffff; padding: 5px 8px; margin: 14px 0 0; }
    table.infos { width: 100%; border-collapse: collapse; }
    table.infos td { padding: 6px 8px; border-bottom: 1px solid #dfe9e1; }
    table.infos td.label { width: 38%; color: #5e6f65; }
    table.infos td.valeur { font-weight: bold; }
    .statut { display: inline-block; padding: 3px 8px; background: #fff3cd; color: #7a5a00; border-radius: 4px; font-weight: bold; }
    .etapes { margin-top: 16px; padding: 10px 12px; background: #f7f9f6; border-left: 4px solid #d8aa2e; }
    .etapes p { margin: 0 0 6px; }
    .etapes ol { margin: 0; padding-left: 16px; }
    .etapes li { margin-bottom: 4px; }
    .pied { position: fixed; bottom: -8px; left: 0; right: 0; text-align: center; font-size: 8.5px; color: #71887b; border-top: 1px solid #dfe9e1; padding-top: 6px; }
  </style>
</head>
<body>
  <table class="bandeau">
    <tr>
      <td class="armoiries"><img src="{{ public_path('assets/images/armoiries-pdf.png') }}" alt="" style="height: 64px;"></td>
      <td>
        <div class="entete-sous">Burkina Faso</div>
        <div class="entete-titre">Ministère de la Famille et de la Solidarité</div>
        <div class="entete-sous">Programme de prise en charge des Orphelins et Enfants Vulnérables</div>
        <div class="drapeau"><span style="background: #e2363f;"></span><span style="background: #1f9d48;"></span></div>
      </td>
    </tr>
  </table>

  <h1>RÉCÉPISSÉ DE SIGNALEMENT</h1>

  <div class="numero-box">
    <div class="numero-label">Numéro de récépissé</div>
    <div class="numero">{{ $signalement->recepisse }}</div>
  </div>
  <div class="date">Signalement enregistré le {{ $signalement->created_at->format('d/m/Y à H\hi') }}</div>

  <h2>Enfant signalé</h2>
  <table class="infos">
    <tr><td class="label">Nom et prénom(s)</td><td class="valeur">{{ $signalement->enfant_nom_complet }}</td></tr>
    <tr><td class="label">Âge estimé</td><td class="valeur">{{ $signalement->enfant_age }} ans</td></tr>
    <tr><td class="label">Situation</td><td class="valeur">{{ $signalement->vulnerabilite_libelle }}</td></tr>
    <tr><td class="label">Localité</td><td class="valeur">{{ $signalement->localite }}, {{ $signalement->province }} ({{ $signalement->region }})</td></tr>
  </table>

  <h2>Déclarant</h2>
  <table class="infos">
    <tr><td class="label">Nom et prénom(s)</td><td class="valeur">{{ $signalement->declarant_nom_complet }}</td></tr>
    <tr><td class="label">Lien avec l'enfant</td><td class="valeur">{{ $signalement->lien_libelle }}</td></tr>
    <tr><td class="label">Téléphone</td><td class="valeur">{{ $signalement->declarant_telephone }}</td></tr>
    <tr><td class="label">Statut du signalement</td><td class="valeur"><span class="statut">En attente d'examen</span></td></tr>
  </table>

  <div class="etapes">
    <p><strong>Et maintenant ?</strong></p>
    <ol>
      <li>Conservez précieusement ce récépissé et son numéro.</li>
      <li>Un agent du programme OEV examine votre signalement.</li>
      <li>Suivez la réponse sur la plateforme, rubrique « Suivi du signalement », avec le numéro <strong>{{ $signalement->recepisse }}</strong> : {{ route('public.suivi') }}</li>
      <li>Si le signalement est validé, un agent vous contactera au {{ $signalement->declarant_telephone }} pour convenir d'une visite au domicile de l'enfant.</li>
    </ol>
  </div>

  <div class="pied">
    Document généré automatiquement par la plateforme OEV — Guichet d'assistance : +226 25 30 00 00 (appel gratuit, 8h – 16h)
  </div>
</body>
</html>
