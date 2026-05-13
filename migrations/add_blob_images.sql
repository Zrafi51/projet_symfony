-- Migration pour stocker les images en BLOB dans la base de données
-- Cela permet à Java et Symfony de partager les mêmes images

-- 1. Ajouter colonne BLOB pour les posts
ALTER TABLE sf_posts 
ADD COLUMN photo_blob LONGBLOB NULL COMMENT 'Image stockée en base de données',
ADD COLUMN photo_mime_type VARCHAR(100) NULL COMMENT 'Type MIME de l\'image (image/jpeg, image/png, etc.)';

-- 2. Ajouter colonne BLOB pour les images multiples
ALTER TABLE sf_images 
ADD COLUMN image_blob LONGBLOB NULL COMMENT 'Image stockée en base de données',
ADD COLUMN mime_type VARCHAR(100) NULL COMMENT 'Type MIME de l\'image';

-- 3. Ajouter colonne BLOB pour les photos de profil
ALTER TABLE sf_users 
ADD COLUMN profile_photo_blob MEDIUMBLOB NULL COMMENT 'Photo de profil en base de données',
ADD COLUMN profile_photo_mime_type VARCHAR(100) NULL COMMENT 'Type MIME de la photo';

-- 4. Ajouter colonne BLOB pour les stories
ALTER TABLE sf_stories 
ADD COLUMN media_blob LONGBLOB NULL COMMENT 'Média de story en base de données',
ADD COLUMN media_mime_type VARCHAR(100) NULL COMMENT 'Type MIME du média';

-- Note: Les colonnes existantes (chemin_photo, filename, etc.) sont conservées
-- pour compatibilité. Priorité sera donnée au BLOB si présent.
