document.addEventListener("DOMContentLoaded", function () {
    gsap.registerPlugin(); // We won't need CSSRulePlugin if not animating pseudo-elements

    const container = document.querySelector(".team-members-container");
    let members = Array.from(document.querySelectorAll(".team-member"));

    // ❓ How many columns per row
    const columns = parseInt(container.dataset.columns) || 3;

    // Store original order for each item
    members.forEach((m, idx) => {
        m.dataset.id = `card-${idx}`;         // Unique ID
        m.dataset.originalOrder = idx + 1;    // For fallback or collapse
        m.style.order = idx + 1;             // Initialize
    });

    /**
     * Function that converts the linear array of members
     * into row-based layout, then does SHIFT as you described.
     */
    function layoutAfterExpand(clickedIndex) {
        let expandedCard = members[clickedIndex];
        let total = members.length;

        // 1) Build row chunks from the current linear order
        // We'll use the .style.order (numbers) to figure out the "current" linear array
        // But simpler is to keep a separate array-based "model".
        // We'll keep `members` array as the "model" itself.
        members.sort((a, b) => {
            return parseInt(a.style.order) - parseInt(b.style.order);
        });

        // 2) Identify row of the clicked card
        // In the sorted array, find the index of the clickedCard
        let newIndex = members.indexOf(expandedCard);
        let rowIndex = Math.floor(newIndex / columns);

        // The row is from rowIndex*columns to rowIndex*columns + columns - 1
        let rowStart = rowIndex * columns;
        let rowEnd = rowStart + columns; // not inclusive

        // 3) Extract that row
        let row = members.slice(rowStart, rowEnd);
        // remove the clicked card from that row
        let leftoverRow = row.filter(m => m !== expandedCard);

        // 4) The next row starts at rowEnd. We'll SHIFT leftoverRow items into the next row,
        // which might push items from the next row into the row after that, etc.
        // So let's build a combined array: everything before rowStart, then [expandedCard],
        // then leftoverRow + the entire remainder from rowEnd onward
        let before = members.slice(0, rowStart);
        let after = members.slice(rowEnd);

        // Insert leftoverRow at the front of "after"
        let combined = [...before, expandedCard, ...leftoverRow, ...after];

        // Now we need to rebuild the final layout in row chunks of size columns.
        // We don't necessarily want the expanded item alone in that row if leftoverRow is big, 
        // but per your requirement: "the row that item is in becomes only that item."
        // So that means we forcibly let row0 = [expandedCard], row1 = leftoverRow + the first part of after, etc.

        // Actually, the approach above is enough to put the expanded card alone in that row.
        // But leftoverRow is in the same row in the array right now. So let's finalize the SHIFT approach:
        // The new array is [before..., expandedCard, leftoverRow, after...].
        // expandedCard is alone in that row => means the next chunk must start at indexOf(expandedCard)+1
        // We'll do a final pass that re-chunks the array in size columns, ignoring the leftover first chunk if it has < columns.

        // For your exact logic:
        // row0: [A, B, C], if B is expanded => row0 => [B], row1 => [A, C, D], row2 => [E, F], etc.
        // So we forcibly place expandedCard in the array first, then leftoverRow is appended to the rest.

        // The above "combined" approach might not do exactly that. We'll do a simpler SHIFT approach:

        // SHIFT approach:
        //  - expandedCard is alone in that row
        //  - leftoverRow is inserted at start of after
        // => combined = [before..., expandedCard, ...leftoverRow, ...after]
        // Then we rename them from 1..n

        // Rebuild the final array:
        let finalArray = [...before, expandedCard, ...leftoverRow, ...after];

        // 5) Reassign .style.order based on finalArray
        finalArray.forEach((card, idx) => {
            gsap.to(card, {
                order: idx + 1,
                duration: 0.3,
                ease: "power2.out"
            });
        });

        // 6) Animate the expandedCard to full width
        gsap.to(expandedCard, {
            flex: "1 1 100%",
            maxWidth: "100%",
            duration: 2.5,
            ease: "elastic.out(1, 0.5)"
        });

        // 7) Animate leftoverRow, if desired, e.g. normal sized
        leftoverRow.forEach(card => {
            gsap.to(card, {
                flex: "1 1 calc(100% / var(--columns, 4) - var(--gap, 20px))",
                maxWidth: "calc(100% / var(--columns, 4) - var(--gap, 20px))",
                duration: 0.3,
                ease: "power2.out"
            });
        });
    }

    // On click:
    members.forEach((member, i) => {
        member.addEventListener("click", function () {
            // If it's already expanded, let's restore the entire original order
            if (member.classList.contains("expanded")) {
                // collapse
                console.log("🔽 Collapse the same card", i);

                // Remove expanded
                member.classList.remove("expanded");

                // restore entire original order
                members.forEach((m, idx) => {
                    gsap.to(m, {
                        order: m.dataset.originalOrder,
                        flex: "1 1 calc(100% / var(--columns, 4) - var(--gap, 20px))",
                        maxWidth: "calc(100% / var(--columns, 4) - var(--gap, 20px))",
                        duration: 0.4,
                        ease: "power2.out"
                    });
                });
            } else {
                // expand
                console.log("🔼 Expand card", i);

                // collapse any currently expanded
                let expanded = document.querySelector(".team-member.expanded");
                if (expanded) {
                    expanded.classList.remove("expanded");
                }

                member.classList.add("expanded");
                layoutAfterExpand(i);
            }
        });
    });
});
