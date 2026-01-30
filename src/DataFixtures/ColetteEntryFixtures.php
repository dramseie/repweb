<?php

namespace App\DataFixtures;

use App\Entity\ColetteEntry;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class ColetteEntryFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $entries = [
            [
                'slug' => 'tisane-aube-rosmarinus',
                'title' => 'Tisane de l\'aube au romarin',
                'subtitle' => 'Un réveil doux pour corps fatigués',
                'category' => ColetteEntry::CATEGORY_RECIPE,
                'content' => <<<TXT
Ingrédients
- 2 brins de romarin frais
- 1 petite branche de thym-citron
- 1 lamelle de zeste d\'orange douce
- 1 cuillerée de miel sombre

Préparation
1. Plonger les herbes dans une eau frémissante et couvrir.
2. Patienter huit minutes, juste le temps que la vapeur embaume la pièce.
3. Filtrer, adoucir avec le miel et inspirer profondément avant la première gorgée.

Rituel
Colette boit cette tisane face à l\'Est, mains posées sur la tasse afin d\'y déposer ses intentions du jour.
TXT,
                'sourceNotes' => "Tradition matinale transmise par Héloïse de la vallée d'Auray, curandera bretonne.",
            ],
            [
                'slug' => 'soupe-pierre-chaude',
                'title' => 'Soupe de pierre chaude',
                'subtitle' => 'Pour les veillées de bivouac',
                'category' => ColetteEntry::CATEGORY_RECIPE,
                'content' => <<<TXT
Ingrédients
- 1 pierre de rivière parfaitement lisse, chauffée dans les braises
- 1 bouquet de bourrache
- 2 échalotes ciselées
- 1 poignée de lentilles corail
- Sel fumé et pluie de poivre long

Préparation
1. Verser lentilles et herbes dans un pot d\'eau froide.
2. Ajouter la pierre chauffée jusqu\'à ébullition franche.
3. Laisser la pierre infuser quinze minutes, retirer puis assaisonner.

Rituel
Autour du feu, chacun adresse un souhait à la pierre avant de la plonger. Le premier bol revient à la gardienne du campement.
TXT,
                'sourceNotes' => 'Technique partagée par les passeurs des Cévennes lors de la transhumance d\'été.',
            ],
            [
                'slug' => 'grimoire-etoiles-hivernales',
                'title' => 'Guide stellaire des nuits d\'hiver',
                'subtitle' => 'Lire les cieux pour prévoir les froids',
                'category' => ColetteEntry::CATEGORY_KNOWLEDGE,
                'content' => <<<TXT
Observation
- La bande laiteuse orientée sud-est annonce trois jours de gel.
- Si Aldébaran palpite de rouge, sortir les couvertures de laine.
- Un halo pâle autour de la lune prédit la neige d\'ici l\'aube.

Transmission
Colette trace ces repères sur un carnet de cuir, griffonné de symboles hérités de sa grand-mère astronome.
TXT,
                'sourceNotes' => 'Combinaison de relevés effectués au refuge de Vercors, hiver 1978-1979.',
            ],
            [
                'slug' => 'respiration-des-anciens',
                'title' => 'Respiration des anciens',
                'subtitle' => 'Aligner le souffle avec la terre',
                'category' => ColetteEntry::CATEGORY_KNOWLEDGE,
                'content' => <<<TXT
Pratique
1. S\'asseoir dos à un arbre, paumes vers le ciel.
2. Inspirer sur quatre temps en imaginant une lumière qui remonte des racines.
3. Suspendre le souffle deux battements, puis expirer sur six temps.
4. Répéter sept fois, à l\'aube ou au crépuscule.

Effets
Apaise les mains qui tremblent et recentre les voyageurs avant une longue traversée.
TXT,
                'sourceNotes' => 'Enseigné par un cercle de guides du Val d\'Aspe, lors des veillées de solstice.',
            ],
            [
                'slug' => 'galette-brisures-etoilees',
                'title' => 'Galette aux brisures étoilées',
                'subtitle' => 'Dessert de fête pour veilleurs de phares',
                'category' => ColetteEntry::CATEGORY_RECIPE,
                'content' => <<<TXT
Ingrédients
- 200 g de farine de châtaigne
- 80 g de beurre salé froid
- 2 jaunes d\'oeuf
- 1 cuillerée de miel de bruyère
- 1 poignée de noisettes concassées
- Zestes de citron confit

Préparation
1. Sabler farine et beurre, ajouter miel et jaunes.
2. Étaler en disque, parsemer de noisettes et zestes.
3. Cuire 18 minutes à four vif jusqu\'à dorure cuivrée.

Rituel
Servir tiède, accompagné d\'une chantilly légèrement fumée au bois de cade.
TXT,
                'sourceNotes' => 'Archivé dans la cuisine du phare de Saint-Mathieu, carnet des veilleuses 1924.',
            ],
        ];

        foreach ($entries as $data) {
            $entry = (new ColetteEntry())
                ->setSlug($data['slug'])
                ->setTitle($data['title'])
                ->setCategory($data['category'])
                ->setContent(trim($data['content']))
                ->setIsPublished(true);

            if (!empty($data['subtitle'])) {
                $entry->setSubtitle($data['subtitle']);
            }

            if (!empty($data['sourceNotes'])) {
                $entry->setSourceNotes($data['sourceNotes']);
            }

            $manager->persist($entry);
        }

        $manager->flush();
    }
}
