<?php

namespace Database\Seeders;

use App\Models\Province;
use Illuminate\Database\Seeder;

class CommuneSeeder extends Seeder
{
    /**
     * Liste officielle des 351 communes du Burkina Faso, groupées par province.
     *
     * Source : « Liste des communes selon leur statut » — Annexe 1 (communes
     * de moyen exercice), Annexe 2 (communes de plein exercice), Annexe 3
     * (communes à statut particulier : Ouagadougou, Bobo-Dioulasso).
     * Le statut n'est pas repris ici, seuls les noms sont importés.
     *
     * Chaque clé est le nom EXACT de la province tel qu'enregistré par
     * LocaliteSeeder.
     */
    private array $communesParProvince = [
        'Mouhoun' => ['Douroula', 'Bondokuy', 'Dédougou', 'Kona', 'Ouarkoye', 'Safané', 'Tchériba'],
        'Balé' => ['Pa', 'Oury', 'Siby', 'Bagassi', 'Bana', 'Boromo', 'Fara', 'Pompoï', 'Poura', 'Yaho'],
        'Banwa' => ['Balavé', 'Kouka', 'Sami', 'Sanaba', 'Tansila', 'Solenzo'],

        'Bougouriba' => ['Gbondjigui', 'Dolo', 'Nioronioro', 'Tiankoura', 'Diébougou'],
        'Ioba' => ['Guéguéré', 'Koper', 'Niégo', 'Oronkua', 'Zambo', 'Dano', 'Dissihn', 'Ouéssa'],
        'Noumbiel' => ['Kpéré', 'Legmoin', 'Midebdo', 'Batié', 'Boussoukoula'],
        'Poni' => ['Bouroum-Bouroum', 'Bousséra', 'Djigouè', 'Gbomblora', 'Malba', 'Nako', 'Périgban', 'Gaoua', 'Kampti', 'Loropéni'],

        'Gourma' => ['Matiacoali', 'Tibga', 'Yamba', 'Diabo', 'Diapangou', "Fada N'Gourma"],
        'Kompienga' => ['Madjoari', 'Pama', 'Kompienga'],

        'Houet' => ['Koundougou', 'Faramana', 'Fô', 'Léna', 'Padéma', 'Satiri', 'Bama', 'Dandé', 'Karangasso-Viguè', 'Karangasso-Sambla', 'Péni', 'Toussiana', 'Bobo-Dioulasso'],
        'Kénédougou' => ['Banzon', 'Djigouèra', 'Kayan', 'Kourinion', 'Morolaba', 'Samôgôyiri', 'Samorogouan', 'Sindo', 'Kangala', 'Koloko', 'Kourouma', "N'Dorola", 'Orodara'],
        'Tuy' => ['Békuy', 'Béréba', 'Boni', 'Koti', 'Fouzan', 'Houndé', 'Koumbia'],

        'Kadiogo' => ['Komki-Ipala', 'Komsilga', 'Koubri', 'Pabré', 'Tanghin-Dassouri', 'Saaba', 'Ouagadougou'],

        'Bam' => ['Bourzanga', 'Nasséré', 'Rollo', 'Zimtenga', 'Tikaré', 'Rouko', 'Guibaré', 'Kongoussi', 'Sabcé'],
        'Namentenga' => ['Boala', 'Dargo', 'Nagbingou', 'Zéguédéguin', 'Boulsa', 'Bouroum', 'Tougouri', 'Yalgo'],
        'Sandbondtenga' => ['Barsalogho', 'Dablo', 'Namissiguima', 'Pensa', 'Pibaoré', 'Ziga', 'Boussouma', 'Kaya', 'Korsimoro', 'Mané', 'Pissila'],

        'Oudalan' => ['Déou', 'Oursi', 'Tinakoff', 'Gorom-Gorom', 'Markoye'],
        'Séno' => ['Bani', 'Gorgadji', 'Sampelga', 'Seytenga', 'Dori', 'Falangountou'],
        'Yagha' => ['Boundoré', 'Mansila', 'Sebba', 'Solhan', 'Tankougounadié', 'Titabé'],

        'Boulgou' => ['Bané', 'Boussouma', 'Zoaga', 'Bagré', 'Béguédo', 'Bittou', 'Bissiga', 'Garango', 'Komtoèga', 'Niaogho', 'Tenkodogo', 'Zabré', 'Zonsé'],
        'Koulpélogo' => ['Dourtenga', 'Lalgaye', 'Sangha', 'Soudougui', 'Yondé', 'Comin-Yanga', 'Ouargaye', 'Yargatenga'],
        'Kourittenga' => ['Baskouré', 'Gounghin', 'Kando', 'Tansobentenga', 'Yargo', 'Andemtenga', 'Dialgaye', 'Koupèla', 'Pouytenga'],

        'Boulkiemdé' => ['Bingo', 'Imasgo', 'Kindi', 'Nandiala', 'Nanoro', 'Pella', 'Ramongo', 'Siglé', 'Soaw', 'Sourgou', 'Thiou', 'Koudougou', 'Poa', 'Sabou', 'Kokologo'],
        'Sanguié' => ['Dassa', 'Didyr', 'Godyr', 'Kordié', 'Kyon', 'Pouni', 'Zamo', 'Zawara', 'Réo', 'Ténado'],
        'Sissili' => ['Nébiélianayou', 'Niambouri', 'Silly', 'Biéha', 'Boura', 'Léo', 'Tô'],
        'Ziro' => ['Bakata', 'Bougnounou', 'Dalô', 'Gaô', 'Kassou', 'Sapouy'],

        'Bazèga' => ['Gaongo', 'Doulougou', 'Ipelcé', 'Kayao', 'Kombissiri', 'Saponé', 'Toécé'],
        'Nahouri' => ['Zecco', 'Guiaro', 'Pô', 'Tiébélé', 'Ziou'],
        'Zoundwéogo' => ['Béré', 'Bindé', 'Gogo', 'Gomboussougou', 'Guiba', 'Manga', 'Nobéré'],

        'Bassitenga' => ['Absouya', 'Dapélogo', 'Ourgou-Manega', 'Loumbila', 'Nagréongo', 'Ziniaré', 'Zitenga'],
        'Ganzourgou' => ['Kogho', 'Méguet', 'Salogo', 'Boudry', 'Mogtédo', 'Zam', 'Zorgho', 'Zoungou'],
        'Kourwéogo' => ['Niou', 'Toéghin', 'Boussé', 'Laye', 'Sourgoubila'],

        'Gnagna' => ['Coalla', 'Liptougou', 'Thion', 'Bilanga', 'Bogandé', 'Mani', 'Pièla'],
        'Komondjari' => ['Bartiébougou', 'Gayéri', 'Foutouri'],

        'Djelgodji' => ['Baraboulé', 'Diguel', 'Kelbo', 'Nassoumbou', 'Pobé-Mengao', 'Tongomayel', 'Djibo'],
        'Karo-Peli' => ['Koutougou', 'Arbinda'],

        'Koosin (Kossi)' => ['Barani', 'Bomborokuy', 'Bourasso', 'Djibasso', 'Dokuy', 'Doumbala', 'Kombori', 'Madouba', 'Sono', 'Nouna'],
        'Nayala' => ['Gassan', 'Gossina', 'Kougny', 'Yaba', 'Yé', 'Toma'],
        'Sourou' => ['Di', 'Gomboro', 'Kassoum', 'Kiembara', 'Lanfièra', 'Lankoué', 'Toéni', 'Tougan'],

        'Comoé' => ['Bérégadougou', 'Moussodougou', 'Ouô', 'Soubakaniédougou', 'Tiéfora', 'Banfora', 'Mangodara', 'Niangoloko', 'Sidéradougou'],
        'Léraba' => ['Douna', 'Kankalaba', 'Loumana', 'Ouéléni', 'Wolonkoto', 'Dakôrô', 'Niankôrôdougou', 'Sindou'],

        'Dyamongou' => ['Botou', 'Kantchari'],
        'Gobnangou' => ['Logobou', 'Namounou', 'Tambaga', 'Tansarga', 'Diapaga', 'Partiaga'],

        'Loroum' => ['Banh', 'Ouindigui', 'Titao', 'Sollé'],
        'Passoré' => ['Arbollé', 'Bagaré', 'Gompomsom', 'Kirsi', 'La-Toden', 'Pilimpikou', 'Samba', 'Bokin', 'Yako'],
        'Yatenga' => ['Kalsaka', 'Kaïn', 'Kossouka', 'Koumbri', 'Rambo', 'Tangaye', 'Thiou', 'Zogoré', 'Barga', 'Namissiguima', 'Ouahigouya', 'Oula', 'Séguénéga'],
        'Zondoma' => ['Bassi', 'Boussou', 'Léba', 'Tougo', 'Gourci'],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ($this->communesParProvince as $provinceNom => $communes) {
            $province = Province::where('nom', $provinceNom)->first();

            if (! $province) {
                $this->command?->warn("Province introuvable, communes ignorées : {$provinceNom}");

                continue;
            }

            foreach ($communes as $nom) {
                $province->communes()->firstOrCreate(['nom' => $nom]);
            }
        }
    }
}
