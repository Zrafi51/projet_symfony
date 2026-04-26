# Migration Java vers Symfony

## Base de donnees a reutiliser

- Le projet Java utilise majoritairement `utils.DataSource`.
- Cette classe pointe vers `jdbc:mysql://localhost:3306/voyage`.
- Deux services speciaux (`AgentSessionService`, `PromptService`) passent par `tn.edu.esprit.tools.DataSource`, qui tente `easy_travel` puis bascule sur `voyage`.
- Dans Symfony, la configuration par defaut a donc ete alignee sur `voyage`.

## CRUD Java identifies

- `destinations`
- `activites`
- `user`
- `reclamation`
- `reponse`
- `paiements`
- `factures`
- `packages`
- `travel_packages`
- `sponsor`
- `voyage`
- `transactions`
- `voyage_history`
- `favorite_packages`
- `notifications`
- `remember_me_devices`
- `sessions`
- `messages`
- `prompts`
- `prompt_versions`

## Ce qui est deja migre

- Infrastructure Symfony avec connexion PDO MySQL
- Rendu HTML simple sans Twig
- CRUD `destinations`
- CRUD `activites`

## Prochaine etape conseillee

1. Migrer `user` pour disposer des comptes, roles et du solde.
2. Migrer `reclamation` et `reponse` car ils dependent de `user`.
3. Migrer `packages`, `paiements` et `factures`.
4. Migrer enfin les modules plus specifiques: IA, prompts, dashboard et packages dynamiques.
