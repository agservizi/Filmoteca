const config = window.appConfig ?? {};
const basePath = typeof config.basePath === 'string' ? config.basePath : '';
const absoluteBase = typeof config.absoluteBase === 'string' ? config.absoluteBase : '';

const resolvePath = (relativePath = '/') => {
  const normalized = relativePath.startsWith('/') ? relativePath : `/${relativePath}`;
  if (absoluteBase) {
    return `${absoluteBase}${normalized}`;
  }
  return `${basePath}${normalized}`;
};

const themeToggle = document.getElementById('theme-toggle');
const storedTheme = localStorage.getItem('filmoteca-theme');
const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

if (storedTheme) {
  document.body.dataset.theme = storedTheme;
} else {
  document.body.dataset.theme = prefersDark ? 'dark' : 'light';
}

const applyTheme = (theme) => {
  document.body.dataset.theme = theme;
  localStorage.setItem('filmoteca-theme', theme);
  themeToggle?.setAttribute('aria-pressed', String(theme === 'dark'));
};

themeToggle?.addEventListener('click', () => {
  const newTheme = document.body.dataset.theme === 'dark' ? 'light' : 'dark';
  applyTheme(newTheme);
});

const burger = document.querySelector('.navbar-burger');
const navMenu = document.getElementById('navMenu');

burger?.addEventListener('click', () => {
  const expanded = burger.getAttribute('aria-expanded') === 'true';
  burger.setAttribute('aria-expanded', String(!expanded));
  burger.classList.toggle('is-active');
  navMenu?.classList.toggle('is-active');
});

const searchInput = document.getElementById('search');
const resultsGrid = document.querySelector('.columns.is-multiline');
let debounceTimer;
let activeController;

const escapeHtml = (value = '') =>
  String(value)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');

const renderMovies = (payload) => {
  if (!resultsGrid) return;
  resultsGrid.innerHTML = '';
  payload.data.forEach((movie) => {
    const column = document.createElement('div');
    column.className = 'column is-one-quarter-desktop is-one-third-tablet is-half-mobile';
    column.innerHTML = `
      <article class="movie-card" data-movie-id="${escapeHtml(movie.id)}">
  <a class="movie-card__link" href="${resolvePath(`/film/${escapeHtml(movie.id)}/${escapeHtml(movie.slug)}`)}">
          <figure class="movie-card__poster">
            ${movie.poster?.url ? `<img src="${escapeHtml(movie.poster.url)}" alt="Poster di ${escapeHtml(movie.title)}" loading="lazy" decoding="async">` : '<div class="movie-card__poster-placeholder" role="img" aria-label="Poster non disponibile"></div>'}
          </figure>
          <div class="movie-card__body">
            <h2 class="movie-card__title">${escapeHtml(movie.title)}</h2>
            <p class="movie-card__meta">
              <span class="movie-card__year">${escapeHtml(movie.year)}</span>
              <span class="movie-card__genre">${escapeHtml(movie.genre ?? '')}</span>
            </p>
            <p class="movie-card__excerpt">${escapeHtml(movie.summary ?? '')}</p>
            <p class="movie-card__rating"><span class="icon">⭐</span><strong>${escapeHtml(Number(movie.rating ?? 0).toFixed(1))}</strong><span class="movie-card__rating-count">(${escapeHtml(movie.rating_count ?? 0)})</span></p>
            <span class="movie-card__cta">Dettagli</span>
          </div>
        </a>
      </article>`;
    resultsGrid.appendChild(column);
  });
};

const fetchMovies = (term = '') => {
  if (activeController) {
    activeController.abort();
  }
  const controller = new AbortController();
  activeController = controller;
  const params = new URLSearchParams(window.location.search);
  if (term) {
    params.set('search', term);
  }
  const requestUrl = `${resolvePath('/api/movies.php')}?${params.toString()}`;
  fetch(requestUrl, {
    headers: {
      Accept: 'application/json'
    },
    signal: controller.signal
  })
    .then((res) => res.json())
    .then((json) => {
      renderMovies(json);
      activeController = null;
    })
    .catch((error) => {
      if (error.name !== 'AbortError') {
        console.error('API fetch failed', error);
      }
      activeController = null;
    });
};

searchInput?.addEventListener('input', (event) => {
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(() => {
    const term = event.target.value.trim();
    if (term.length >= 3 || term.length === 0) {
      fetchMovies(term);
    }
  }, 300);
});

const shareButtons = document.querySelectorAll('[data-action="share"]');
shareButtons.forEach((button) => {
  button.addEventListener('click', async () => {
    const shareUrl = button.getAttribute('data-share-url');
    if (navigator.share) {
      try {
        await navigator.share({
          title: document.title,
          text: 'Guarda questo film su Filmoteca Pro',
          url: shareUrl
        });
      } catch (error) {
        console.error('Share dismissed', error);
      }
    } else if (shareUrl) {
      await navigator.clipboard?.writeText(shareUrl);
      button.classList.add('is-success');
      button.querySelector('span:last-child').textContent = 'Link copiato!';
      setTimeout(() => {
        button.classList.remove('is-success');
        button.querySelector('span:last-child').textContent = 'Condividi';
      }, 1600);
    }
  });
});

const watchlistButtons = document.querySelectorAll('[data-action="watchlist"]');
const watchlist = new Set(JSON.parse(localStorage.getItem('filmoteca-watchlist') ?? '[]'));

const persistWatchlist = () => {
  localStorage.setItem('filmoteca-watchlist', JSON.stringify(Array.from(watchlist)));
};

watchlistButtons.forEach((button) => {
  const movieId = Number(button.getAttribute('data-movie-id'));
  if (watchlist.has(movieId)) {
    button.classList.add('is-success');
    button.querySelector('span:last-child').textContent = 'Nella watchlist';
  }
  button.addEventListener('click', () => {
    if (watchlist.has(movieId)) {
      watchlist.delete(movieId);
      button.classList.remove('is-success');
      button.querySelector('span:last-child').textContent = 'Aggiungi alla watchlist';
    } else {
      watchlist.add(movieId);
      button.classList.add('is-success');
      button.querySelector('span:last-child').textContent = 'Nella watchlist';
    }
    persistWatchlist();
  });
});
