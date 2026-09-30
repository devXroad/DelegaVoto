document.addEventListener('DOMContentLoaded', async () => {
    const select = document.getElementById('candidateSelect');
    const btnVote = document.getElementById('btnVote');
    const errorMsg = document.getElementById('errorMessage');
    const votingSection = document.getElementById('votingSection');
    const successSection = document.getElementById('successSection');
    const urnaContainer = document.getElementById('urnaContainer');
    const voteTicket = document.getElementById('voteTicket');
    const onlineIndicator = document.getElementById('onlineIndicator');
    
    let candidates = [];

    // Heartbeat de usuarios en línea
    async function updateOnlineStatus() {
        try {
            const res = await fetch('api.php?action=heartbeat');
            const data = await res.json();
            if (onlineIndicator && data.online !== undefined) {
                onlineIndicator.innerHTML = `<svg width="10" height="10" viewBox="0 0 24 24" fill="#2ecc71" style="vertical-align: middle; margin-right: 4px;"><circle cx="12" cy="12" r="8"></circle></svg> ${data.online} conectado${data.online !== 1 ? 's' : ''}`;
            }
        } catch(e){}
    }
    updateOnlineStatus();
    setInterval(updateOnlineStatus, 5000);

    // Estado inicial
    if (typeof USER_HAS_VOTED !== 'undefined' && USER_HAS_VOTED) {
        showSuccessState();
    }

    async function loadCandidates() {
        if (!select || (typeof USER_HAS_VOTED !== 'undefined' && USER_HAS_VOTED)) return;
        try {
            const res = await fetch('api.php?action=candidates');
            if (res.ok) {
                const newCandidates = await res.json();
                if (newCandidates.length !== candidates.length) {
                    candidates = newCandidates;
                    const selectedVal = select.value;
                    select.innerHTML = `<option value="" selected disabled>Selecciona a alguien... (${candidates.length} en lista)</option>`;
                    candidates.forEach(c => {
                        const opt = document.createElement('option');
                        opt.value = c.name;
                        opt.textContent = c.name;
                        select.appendChild(opt);
                    });
                    if (selectedVal && candidates.some(c => c.name === selectedVal)) {
                        select.value = selectedVal;
                    }
                }
            }
        } catch (e) {
            console.error('Error fetching candidates', e);
        }
    }

    loadCandidates();
    setInterval(loadCandidates, 3000);

    if (btnVote) {
        btnVote.addEventListener('click', async () => {
            const val = select.value.trim();
            if (!val) {
                errorMsg.innerText = 'Por favor, selecciona un/a candidato/a.';
                return;
            }
            
            errorMsg.innerText = '';
            btnVote.disabled = true;
            btnVote.style.opacity = '0.7';

            try {
                const res = await fetch('api.php?action=vote', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ candidateName: val })
                });

                const data = await res.json();

                if (res.ok && data.success) {
                    playVoteAnimation(val);
                } else {
                    errorMsg.innerText = data.error || 'Ocurrió un error.';
                    btnVote.disabled = false;
                    btnVote.style.opacity = '1';
                }
            } catch (e) {
                errorMsg.innerText = 'Error de conexión.';
                btnVote.disabled = false;
                btnVote.style.opacity = '1';
            }
        });
    }

    function playVoteAnimation(name) {
        votingSection.classList.add('hide');

        // ANIMACIÓN MÁS RÁPIDA
        setTimeout(() => {
            votingSection.classList.add('hidden');
            urnaContainer.classList.remove('hidden');

            const displayName = name.toUpperCase().substring(0, 20); // margen amplio, el papel se adapta
            voteTicket.textContent = displayName;

            // El papel crece con el nombre (hasta su max-width en CSS); si aun así
            // el nombre es muy largo, reducimos un poco la letra para que no se salga.
            let fontSize = 14;
            if (displayName.length > 16) fontSize = 10;
            else if (displayName.length > 12) fontSize = 11;
            else if (displayName.length > 9) fontSize = 12.5;
            voteTicket.style.fontSize = fontSize + 'px';

            voteTicket.classList.add('fall');
        }, 300); // 300ms (antes 600)

        setTimeout(() => {
            urnaContainer.classList.add('hidden');
            successSection.classList.remove('hidden');
        }, 1300); // 1300ms (antes 3000)
    }

    function showSuccessState() {
        votingSection.classList.add('hidden');
        successSection.classList.remove('hidden');
        successSection.style.animationDelay = '0s';
        const circle = document.querySelector('.check-circle');
        const mark = document.querySelector('.check-mark');
        if (circle) circle.style.animationDelay = '0s';
        if (mark) mark.style.animationDelay = '0.1s';
    }
});
