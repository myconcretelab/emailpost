# Emailpost Plugin

Le plugin Emailpost pour Grav permet de créer automatiquement un article de blog à partir d'un email reçu via Mailgun.

## Fonctionnalités

- Point d'entrée configurable pour recevoir les webhooks Mailgun.
- Page parente configurable pour la création des articles.
- Conversion du sujet et du contenu de l'email en titre et corps de l'article.
- Sauvegarde des pièces jointes comme médias de l'article.
- Utilisation du template `item` par défaut.

## Installation

Copiez le dossier `user/plugins/emailpost` dans votre installation Grav et activez le plugin depuis l'admin ou via le fichier `user/config/plugins/emailpost.yaml`.

## Configuration

```yaml
enabled: true
webhook_route: /emailpost
parent_route: /blog
template: item
```

- **webhook_route** : URL (relative) à laquelle Mailgun doit envoyer les emails.
- **parent_route** : Page parente qui contiendra les nouveaux articles.
- **template** : Template utilisé pour les articles générés (fixé à `item`).

## Utilisation

Configurez Mailgun pour envoyer les emails entrants au `webhook_route`. Le plugin analysera le sujet, le contenu et les pièces jointes (images) pour créer automatiquement un nouvel article sous la page parente spécifiée.
