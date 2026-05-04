document.addEventListener('DOMContentLoaded', () => {
    const emojiButtons = Array.from(document.querySelectorAll('[data-emoji-target]'));
    const fileInputs = Array.from(document.querySelectorAll('[data-file-input]'));
    const commentFocusButtons = Array.from(document.querySelectorAll('[data-comment-focus]'));
    const storyTriggers = Array.from(document.querySelectorAll('[data-story-trigger]'));
    const storyViewer = document.querySelector('[data-story-viewer]');
    const storyCloseButtons = Array.from(document.querySelectorAll('[data-story-close]'));
    const storyNavButtons = Array.from(document.querySelectorAll('[data-story-nav]'));
    const storyImage = document.getElementById('forumStoryViewerImage');
    const storyAuthor = document.getElementById('forumStoryViewerAuthor');
    const storyTime = document.getElementById('forumStoryViewerTime');
    const storyCaption = document.getElementById('forumStoryViewerCaption');
    const storyCount = document.getElementById('forumStoryViewerCount');
    const storyViews = document.getElementById('forumStoryViewerViews');
    const storyAvatarImage = document.getElementById('forumStoryViewerAvatarImage');
    const storyAvatarFallback = document.getElementById('forumStoryViewerAvatarFallback');

    const stories = storyTriggers.map((trigger, index) => ({
        index,
        id: Number(trigger.getAttribute('data-story-id') ?? 0),
        image: trigger.getAttribute('data-story-image') ?? '',
        author: trigger.getAttribute('data-story-author') ?? 'Story',
        time: trigger.getAttribute('data-story-time') ?? 'Maintenant',
        caption: trigger.getAttribute('data-story-caption') ?? 'Nouvelle story',
        avatar: trigger.getAttribute('data-story-avatar') ?? '',
        initial: trigger.getAttribute('data-story-initial') ?? 'S',
        viewUrl: trigger.getAttribute('data-story-view-url') ?? '',
        views: Number(trigger.getAttribute('data-story-views') ?? 0),
        viewCountNode: trigger.closest('.forum-story-card')?.querySelector('[data-story-view-count-label]') ?? null,
        viewTracked: false,
    }));

    let activeStoryIndex = -1;

    const insertAtCursor = (field, value) => {
        if (!field) {
            return;
        }

        const start = field.selectionStart ?? field.value.length;
        const end = field.selectionEnd ?? field.value.length;
        const nextValue = `${field.value.slice(0, start)}${value}${field.value.slice(end)}`;
        field.value = nextValue;
        const cursorPosition = start + value.length;
        field.focus();
        if (typeof field.setSelectionRange === 'function') {
            field.setSelectionRange(cursorPosition, cursorPosition);
        }
    };

    emojiButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const targetId = button.getAttribute('data-emoji-target');
            const target = targetId ? document.getElementById(targetId) : null;
            insertAtCursor(target, button.getAttribute('data-emoji-value') ?? button.textContent ?? '');
        });
    });

    fileInputs.forEach((input) => {
        input.addEventListener('change', () => {
            const file = input.files && input.files[0] ? input.files[0] : null;
            const previewId = input.getAttribute('data-preview-target');
            const nameId = input.getAttribute('data-name-target');
            const preview = previewId ? document.getElementById(previewId) : null;
            const nameTarget = nameId ? document.getElementById(nameId) : null;

            if (nameTarget) {
                nameTarget.textContent = file ? file.name : 'Aucune image';
            }

            if (!preview) {
                return;
            }

            if (!file) {
                preview.hidden = true;
                preview.innerHTML = '';
                return;
            }

            const imageUrl = URL.createObjectURL(file);
            preview.innerHTML = '';
            const image = document.createElement('img');
            image.src = imageUrl;
            image.alt = file.name;
            preview.appendChild(image);
            preview.hidden = false;

            image.addEventListener('load', () => {
                URL.revokeObjectURL(imageUrl);
            });
        });
    });

    commentFocusButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const targetId = button.getAttribute('data-comment-focus');
            const target = targetId ? document.getElementById(targetId) : null;
            if (!target) {
                return;
            }

            target.focus();
            if (typeof target.scrollIntoView === 'function') {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'center',
                });
            }
        });
    });

    const formatViewLabel = (count) => `${count} ${count > 1 ? 'vues' : 'vue'}`;

    const syncStoryViewCount = (story) => {
        if (!story) {
            return;
        }

        if (story.viewCountNode) {
            story.viewCountNode.textContent = formatViewLabel(story.views);
        }

        if (storyViews && activeStoryIndex === story.index) {
            storyViews.textContent = formatViewLabel(story.views);
        }
    };

    const registerStoryView = (story) => {
        if (!story || story.viewTracked || !story.viewUrl) {
            syncStoryViewCount(story);
            return;
        }

        story.viewTracked = true;
        fetch(story.viewUrl, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        })
            .then((response) => {
                if (!response.ok) {
                    throw new Error('Story view request failed');
                }

                return response.json();
            })
            .then((payload) => {
                if (payload && payload.success && Number.isFinite(Number(payload.views))) {
                    story.views = Number(payload.views);
                }
                syncStoryViewCount(story);
            })
            .catch(() => {
                syncStoryViewCount(story);
            });
    };

    const renderStory = (index) => {
        if (!storyViewer || !storyImage || !storyAuthor || !storyTime || !storyCaption || !storyCount || !storyViews || !storyAvatarImage || !storyAvatarFallback) {
            return;
        }

        const story = stories[index];
        if (!story) {
            return;
        }

        activeStoryIndex = index;
        storyImage.src = story.image;
        storyImage.alt = `Story de ${story.author}`;
        storyAuthor.textContent = story.author;
        storyTime.textContent = story.time;
        storyCaption.textContent = story.caption;
        storyCount.textContent = `${index + 1} / ${stories.length}`;
        storyViews.textContent = formatViewLabel(story.views);

        if (story.avatar) {
            storyAvatarImage.src = story.avatar;
            storyAvatarImage.alt = story.author;
            storyAvatarImage.hidden = false;
            storyAvatarFallback.hidden = true;
        } else {
            storyAvatarImage.hidden = true;
            storyAvatarImage.removeAttribute('src');
            storyAvatarFallback.hidden = false;
            storyAvatarFallback.textContent = story.initial;
        }

        storyViewer.hidden = false;
        document.body.style.overflow = 'hidden';
        syncStoryViewCount(story);
        registerStoryView(story);
    };

    const closeStoryViewer = () => {
        if (!storyViewer) {
            return;
        }

        storyViewer.hidden = true;
        activeStoryIndex = -1;
        document.body.style.overflow = '';
    };

    const moveStory = (direction) => {
        if (stories.length === 0) {
            return;
        }

        let nextIndex = activeStoryIndex + direction;
        if (nextIndex < 0) {
            nextIndex = stories.length - 1;
        }
        if (nextIndex >= stories.length) {
            nextIndex = 0;
        }

        renderStory(nextIndex);
    };

    storyTriggers.forEach((trigger, index) => {
        trigger.addEventListener('click', () => {
            renderStory(index);
        });
    });

    storyCloseButtons.forEach((button) => {
        button.addEventListener('click', closeStoryViewer);
    });

    storyNavButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const direction = button.getAttribute('data-story-nav') === 'prev' ? -1 : 1;
            moveStory(direction);
        });
    });

    document.addEventListener('keydown', (event) => {
        if (!storyViewer || storyViewer.hidden) {
            return;
        }

        if (event.key === 'Escape') {
            closeStoryViewer();
        }

        if (event.key === 'ArrowLeft') {
            moveStory(-1);
        }

        if (event.key === 'ArrowRight') {
            moveStory(1);
        }
    });
});
