/* Enhancement only: server validation, submissions and quiz timer remain authoritative. */
(() => {
  const content = document.querySelector('.rb-content');
  if (!content) return;
  const selectors = document.body.classList.contains('rb-page-materi') ? '.kartu-materi' : document.body.classList.contains('rb-page-kuis') ? 'article.kartu-kuis' : document.body.classList.contains('rb-page-forum') ? 'article.forum-kartu' : null;
  const cards = selectors ? [...content.querySelectorAll(selectors)] : [];
  if (cards.length) {
    const search = document.createElement('div');
    search.className = 'rb-search';
    search.innerHTML = '<label for="rbSearch">Cari di halaman</label><input type="search" id="rbSearch" placeholder="Ketik judul atau kata kunci…" autocomplete="off">';
    cards[0].before(search);
    const empty = document.createElement('p'); empty.className='rb-no-results'; empty.hidden=true; empty.setAttribute('role','status'); empty.textContent='Belum ditemukan. Coba kata kunci lainnya.'; search.after(empty);
    search.querySelector('input').addEventListener('input', e => {
      const q = e.target.value.toLocaleLowerCase('id-ID').trim();
      cards.forEach(card => { card.hidden = !card.textContent.toLocaleLowerCase('id-ID').includes(q); });
      empty.hidden = cards.some(card => !card.hidden);
    });
  }
  const quiz = document.getElementById('formKuis');
  if (quiz) {
    const questions = [...quiz.querySelectorAll('.kartu-kuis')];
    const nav = document.createElement('nav'); nav.className='rb-question-nav'; nav.setAttribute('aria-label','Navigasi soal');
    const links = questions.map((question,i) => {
      question.id='rb-soal-'+(i+1);
      const link=document.createElement('a'); link.href='#'+question.id; link.textContent=i+1; link.setAttribute('aria-label','Ke soal '+(i+1)); nav.append(link); return link;
    });
    const progress=document.createElement('span'); progress.className='rb-question-progress'; progress.setAttribute('aria-live','polite'); nav.append(progress); quiz.before(nav);
    function update(){let answered=0;questions.forEach((question,i)=>{const checked=!!question.querySelector('input[type=radio]:checked');links[i].classList.toggle('answered',checked);links[i].setAttribute('aria-label','Ke soal '+(i+1)+(checked?', sudah dipilih':''));if(checked)answered++;});progress.textContent=answered+' / '+questions.length+' soal dipilih';}
    quiz.addEventListener('change',update);update();
  }
})();
