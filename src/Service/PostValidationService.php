<?php

class PostValidationService
{
    public function validatePost(Post $post, Destination $destination): array
    {
        $errors = [];
        
        if ($destination->getSocialAccount()->getPlatform() === 'reddit') {
            $restrictions = $destination->getSettings()['restrictions'] ?? [];
            
            # Validation titre
            if ($titlePattern = $restrictions['title_format']) {
                if (!preg_match($titlePattern, $post->getTitle())) {
                    $errors[] = "Titre ne respecte pas le format requis pour r/{$destination->getName()}";
                }
            }
            
            # Mots interdits
            $content = $post->getTitle() . ' ' . $post->getContent();
            foreach ($restrictions['forbidden_words'] as $word) {
                if (stripos($content, $word) !== false) {
                    $errors[] = "Contenu contient un mot interdit: {$word}";
                }
            }
            
            # Type de submission
            $submissionType = $restrictions['submission_type'];
            $hasMedia = !empty($post->getMediaFiles());
            
            if ($submissionType === 'text' && $hasMedia) {
                $errors[] = "Ce subreddit n'accepte que les posts texte";
            } elseif ($submissionType === 'link' && !$hasMedia) {
                $errors[] = "Ce subreddit n'accepte que les liens";
            }
        }
        
        return $errors;
    }
}